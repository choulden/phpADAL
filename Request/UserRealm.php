<?php
namespace phpADAL\Request;

use phpADAL\Constants;
use phpADAL\Log\Logger;
use phpADAL\HTTP\Request as HTTPRequest;
use phpADAL\Validation\ConstantValidation;

define('USER_REALM_PATH_TEMPLATE', 'common/UserRealm/<user>');

class UserRealm {
 protected $context;
 protected $authority;
 protected $accountType;
 protected $userPrinciple;
 protected $federationProtocol;
 protected $federationMetadataUrl;
 protected $federationActiveAuthUrl;

 protected $apiVersion = '1.0';

 function __construct($context, $userPrinciple) {
  $this->context = $context;
  $this->authority = $context->getAuthority();
  $this->userPrinciple = $userPrinciple;

  $this->accountType = null;
  $this->federationProtocol = null;
  $this->federationMetadataUrl = null;
  $this->federationActiveAuthUrl = null;
 }

 /*
  * Accessors
  */
 public function getApiVersion() {
  return $this->apiVersion;
 }

 public function getFederationProtocol() {
  return $this->federationProtocol;
 }

 public function getAccountType() {
  return $this->accountType;
 }

 public function getFederationMetadataUrl() {
  return $this->federationMetadataUrl;
 }

 public function getFederationActiveAuthUrl() {
  return $this->federationActiveAuthUrl;
 }

 private function encodeURIComponent($str) {
    $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
    return strtr(rawurlencode($str), $revert);
}

 private function getUserRealmUrl() {
  $userRealmUrl = parse_url(http_build_url($this->authority->getUrl()));
  $urlEncodedUser = $this->encodeURIComponent($this->userPrinciple);

  $userRealmUrl['path'] = str_replace('<user>', $urlEncodedUser, USER_REALM_PATH_TEMPLATE);

  $userRealmQuery = array(
   'api-version' => $this->apiVersion
  );

  $userRealmUrl['query'] = http_build_query($userRealmQuery); //json_encode($userRealmQuery);

  $userRealmUrl = parse_url(http_build_url($userRealmUrl));

  return $userRealmUrl;
 }

 private function validateAccountType($type) {
  return ConstantValidation::validate(Constants::constant('UserRealm')['AccountType'], $type);
 }

 private function validateFederationProtocol($protocol) {
  return ConstantValidation::validate(Constants::constant('UserRealm')['FederationProtocolType'], $protocol);
 }

 private function logParsedResponse() {
  Logger::log('UserRealm response:');
  Logger::log('  AccountType: ' . $this->accountType);
  Logger::log('  FederationProtocol: ' . $this->federationProtocol);
  Logger::log('  FederationMetatdataUrl: ' . $this->federationMetadataUrl);
  Logger::log('  FederationActiveAuthUrl: ' . $this->federationActiveAuthUrl);
 }

 private function parseDiscoveryResponse($body) {
  Logger::log('Discovery response:', $body);

  $response = null;

  try {
   $response = (array)json_decode($body);
  } catch (Exception $e) {
   Logger::log('Parsing realm discovery respone JSON failed:', $body);
   return;
  }

  $accountType = $this->validateAccountType($response['account_type']);

  if (!$accountType) {
   Logger::log('Cannot parse account_type:', $accountType);
   return;
  } else {
   $this->accountType = $accountType;
  }

  if ($this->accountType == Constants::constant('UserRealm')['AccountType']['Federated']) {
   $protocol = $this->validateFederationProtocol($response['federation_protocol']);

   if (!$protocol) {
    Logger::log('Cannot parse federation protocol:', $protocol);
    return;
   } else {
    $this->federationProtocol = $protocol;
    $this->federationMetadataUrl = $response['federation_metadata_url'];
    $this->federationActiveAuthUrl = $response['federation_active_auth_url'];
   }
  }

  $this->logParsedResponse();

  return;
 }

 public function discover() {
  $userRealmUrl = $this->getUserRealmUrl();
  Logger::log('Performing user realm discovery at:', $userRealmUrl);

  $request = new HTTPRequest($this->context);

  $request->setURL($userRealmUrl);
  $request->addHeaders(array(
    'Accept' => 'application/json'
   )
  );

  // Send the request
  $response = $request->get();

  if ($request->processRequest($response)) {
   $this->parseDiscoveryResponse($response->getResponseBody());
  }
 }
}
?>
