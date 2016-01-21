<?php
namespace phpADAL\Request;

use phpADAL\Constants;
use phpADAL\Log\Logger;
use phpADAL\Exception\ADALException as Exception;
use phpADAL\HTTP\Request as HTTPRequest;
use UUIDgen\UUID;
use \DateTime;
use \DateInterval;

class OAuth2 {
 private $tokenEndpoint = null;
 private $deviceCodeEndpoint = null;
 private $context = null;
 private $cancelPollingRequest = false;

 private $tokenMap;
 private $deviceCodeMap;

 // Default constructor
 function __construct($context, $authority) {
  $this->tokenEndpoint = $authority->getTokenEndpoint();
  $this->deviceCodeEndpoint = $authority->getDeviceCodeEndpoint();

  $this->context = $context;
  $this->cancelPollingRequest = false;

  // Create the response code maps
  $this->tokenMap = $this->generateMap(Constants::constant('OAuth2')['ResponseParameters'], Constants::constant('TokenResponseFields'));
  $this->deviceCodeMap = $this->generateMap(Constants::constant('OAuth2')['DeviceCodeResponseParameters'], Constants::constant('UserCodeResponseFields'));
 }

 private function generateMap($source, $dest) {
  $map = array();

  foreach($source as $key => $value) {
   if (array_key_exists($key, $dest)) {
    $map[$value] = $dest[$key];
   }
  }

  return $map;
 }

 // Create a URL for the token request
 private function createTokenUrl() {
  $tokenUrl = parse_url($this->tokenEndpoint);

  $param = array(
   Constants::constant('OAuth2')['Parameters']['AAD_API_VERSION'] => '1.0'
  );

  foreach($param as $key => $value) {
   $pStr[] = $key.'='.$value;
  }

  $tokenUrl['query'] = implode($pStr, '&');

  return $tokenUrl;
 }

 // Create a URL for the devicecode request
 private function createDeviceCodeUrl() {
  $deviceCodeUrl = parse_url($this->deviceCodeEndpoint);
  $param = array(
   Constants::constant('OAuth2')['Parameters']['AAD_API_VERSION'] => '1.0'
  );

  $deviceCodeUrl['query'] = http_build_query($param);

  return $deviceCodeUrl;
 }

 // Convert specific values in an array to integers
 private function parseOptionalInts($obj, $keys) {
  foreach ($keys as $key) {
   if (in_array($key, $obj)) {
    $obj[$key] = intval($obj[$key], 10);
    if (!is_numeric($obj[$key])) {
     throw new Exception($key . " could not be parsed as an int.");
    }
   }
  }

  return $obj;
 }

 // Validates and decodes a JSON Web Token
 private function crackJwt($jwtToken) {
/*
  $idTokenPartsRegex = "/^([^\.\s]*)\.([^\.\s]+)\.([^\.\s]*)$/";

  $matches = preg_match($idTokenPartsRegex, $jwtToken);
  if (!$matches || sizeof($matches) < 4) {
   Logger::log("The returned id_token is not parseable.");
   return;
  }

  $crackedToken = array(
   'header'	=>	$matches[1],
   'JWSPayload'	=>	$matches[2],
   'JWSSig'	=>	$matches[3]
  );
*/

  $tokenArr = explode('.', $jwtToken);

  if (sizeof($tokenArr) != 3) {
   Logger::log('The returned id_token is not parseable.');
   return;
  } else {
   $crackedToken = array(
    'header' => $tokenArr[0],
    'JWSPayload' => $tokenArr[1],
    'JWSSig' => $tokenArr[2]
   );
  }
  return $crackedToken;
 }

 // Copies one array to another based on specific fields
 private function mapFields($source, $map) {
  $dest = array();

  foreach($source as $key => $value) {
   if (array_key_exists($key, $map)) {
    $mapKey = $map[$key];
    $dest[$mapKey] = $source[$key];
   }
  }

  return $dest;
 }

 // Retrieve the userId from a token
 private function getUserId($idToken) {
  $userId = '';
  $isDisplayable = false;

  if ($idToken->upn) {
   $userId = $idToken->upn;
   $isDisplayable = true;
  } else if ($idToken->email) {
   $userId = $idToken->email;
   $isDisplayable = true;
  } else if ($idToken->sub) {
   $userId = $idToken->sub;
  }

  if (!$userId) {
   $userId = UUID::generate(4);	// Generate a new version 4 UUID
  }

  $userIdVals = array(
   Constants::constant('IdTokenFields')['USER_ID'] => $userId,
   Constants::constant('IdTokenFields')['IS_USER_ID_DISPLAYABLE'] => $isDisplayable
  );

  return $userIdVals;
 }

 // Extract values from an idToken
 private function extractIdTokenValues($idToken) {
  $vals = $this->getUserId($idToken);

  $map = Constants::constant('OAuth2')['IdTokenMap'];
  $mappedFields = $this->mapFields($vals, $map);

  return array_merge($mappedFields, $vals);
 }

 // Parse an IdToken
 private function parseIdToken($encodedIdToken) {
  $crackedToken = $this->crackJwt($encodedIdToken);
  if (!$crackedToken) {
   return;
  }

  $idToken = null;

  try {
   $base64IdToken = $crackedToken['JWSPayload'];
   $base64Decoded = base64_decode($base64IdToken);

   if (!$base64Decoded) {
    Logger::log('The returned id_token could not be base64 decoded');
    return;
   }
   $idToken = json_decode($base64Decoded);
  } catch(Exception $e) {
   Logger::log('The returned id_token could not be decoded: ' . $e->getMessage());
  }

  return $this->extractIdTokenValues($idToken);
 }

 // Create default posting headers
 private function createDefaultHeaders() {
  return array(
   'Content-Type' => 'application/x-www-form-urlencoded',
   'Charset' => 'utf-8',
  );
 }

 // Handle the Token Request response
 private function handleGetResponseToken($response) {
  $decodedResponse = null;

  try {
   $decodedResponse = json_decode($response);
  } catch(Exception $e) {
   Logger::log('The token response returned from the server is unparseable as JSON');
  }

  $keys = array(
   Constants::constant('OAuth2')['ResponseParameters']['EXPIRES_ON'],
   Constants::constant('OAuth2')['ResponseParameters']['EXPIRES_IN'],
   Constants::constant('OAuth2')['ResponseParameters']['CREATED_ON']
  );

  $decodedResponse = $this->parseOptionalInts((array)$decodedResponse, $keys);

  // Retrieve the EXPIRES_IN value (in seconds) and create an EXPIRES_ON value of (current_date_time + EXPIRES_IN)
  if (array_key_exists(Constants::constant('OAuth2')['ResponseParameters']['EXPIRES_IN'], $decodedResponse)) {
   $expiresIn = $decodedResponse[Constants::constant('OAuth2')['ResponseParameters']['EXPIRES_IN']];
   $now = new DateTime();
   $new_date = $now->add(new DateInterval("PT".$expiresIn."S"));
   $decodedResponse[Constants::constant('OAuth2')['ResponseParameters']['EXPIRES_ON']] = $new_date->format('Y-m-d H:i:s');
  }

  // If the CREATED_ON value was returned, convert it to a DateTime object
  if (array_key_exists(Constants::constant('OAuth2')['ResponseParameters']['CREATED_ON'], $decodedResponse)) {
   $d = new DateTime();
   $createdOn = $decodedResponse[Constants::constant('OAuth2')['ResponseParameters']['CREATED_ON']];
   $createdOn->setTime($decodedResponse[Constants::constant('OAuth2')['ResponseParameters']['CREATED_ON']]);
   $decodedResponse[Constants::constant('OAuth2')['ResponseParameters']['CREATED_ON']] = $createdOn->format('Y-m-d H:i:s');
  }

  if (!array_key_exists(Constants::constant('OAuth2')['ResponseParameters']['TOKEN_TYPE'], (array)$decodedResponse)) {
   throw new Exception('Response is missing token_type');
  }

  if (!array_key_exists(Constants::constant('OAuth2')['ResponseParameters']['ACCESS_TOKEN'], (array)$decodedResponse)) {
   throw new Exception('Response is missing access_token');
  }

  $tokenResponse = $this->mapFields($decodedResponse, $this->tokenMap);

  if (array_key_exists(Constants::constant('OAuth2')['ResponseParameters']['ID_TOKEN'], (array)$decodedResponse)) {
   $idToken = $this->parseIdToken($decodedResponse[Constants::constant('OAuth2')['ResponseParameters']['ID_TOKEN']]);

   if ($idToken) {
    $tokenResponse = array_merge($tokenResponse, $idToken);
   }
  }

  Logger::log('Request\OAuth2\handleGetResponseToken', $tokenResponse);

  return $tokenResponse;
 }

 // Handle the Device Code response
 private function handleGetDeviceCodeResponse($response) {
  $decodedResponse = null;

  try {
   $decodedResponse = json_decode($response);
  } catch(Exception $e) {
   Logger::log('The device code response returned from the server is unparseable as JSON.');
  }

  $keys = array(
   Constants::constant('OAuth2')['DeviceCodeResponseParameters']['EXPIRES_IN'],
   Constants::constant('OAuth2')['DeviceCodeResponseParameters']['INTERVAL']
  );

  $decodedResponse = $this->parseOptionalInts((array)$decodedResponse, $keys);

  if (!array_key_exists(Constants::constant('OAuth2')['DeviceCodeResponseParameters']['EXPIRES_IN'], (array)$decodedResponse)) {
   throw new Exception('Response is missing expires_in');
  }

  if (!array_key_exists(Constants::constant('OAuth2')['DeviceCodeResponseParameters']['DEVICE_CODE'], (array)$decodedResponse)) {
   throw new Exception('Response is missing device_code');
  }

  if (!array_key_exists(Constants::constant('OAuth2')['DeviceCodeResponseParameters']['USER_CODE'], (array)$decodedResponse)) {
   throw new Exception('Response is missing user_code');
  }

  $deviceCodeResponse = $this->mapFields($decodedResponse, $this->deviceCodeMap);

  Logger::log('Request\OAuth2\handleGetDeviceCodeResponse', $deviceCodeResponse);

  return $deviceCodeResponse;
 }

 // Retrieve a token
 public function getToken($params) {
  $request = new HTTPRequest($this->context);

  // Retrieve the URL
  $tokenUrl = $this->createTokenUrl();

  // Set the Request details
  $request->setURL($tokenUrl);
  $request->setDataWithBuilder($params);
  $request->addHeaders($this->createDefaultHeaders());

  // Send the request
  $response = $request->post();

  if ($request->processRequest($response)) {
   return $this->handleGetResponseToken($response->getResponseBody());
  }
 }

 public function getUserCodeInfo($params) {
  $request = new HTTPRequest($this->context);

  // Retrieve the URL
  $deviceCodeUrl = $this->createDeviceCodeUrl();

  // Set the Request details
  $request->setURL($deviceCodeUrl);
  $request->setDataWithBuilder($params);
  $request->addHeaders($this->createDefaultHeaders());

  // Send the request
  $response = $request->post();

  if ($request->processRequest($response)) {
   return $this->handleGetDeviceCodeResponse($response->getResponseBody());
  }
 }
}
?>
