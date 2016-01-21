<?php
namespace phpADAL\Authentication;

use phpADAL\Constants;
use phpADAL\Authentication\Authority;
use phpADAL\Validation\StringValidation;
use phpADAL\Validation\UserCodeValidation;
use phpADAL\Request\Token as TokenRequest;
use phpADAL\Request\Code as CodeRequest;
use phpADAL\Log\Logger;
use UUIDgen\UUID; 

class AuthenticationContext {
 private $authority;
 private $correlationId = null;
 private $cache;
 private $tokenRequestWithUserCode = array();

 private $debug;

 function __construct($authorityUrl, $validateAuthority=true, $cache=null) {
  $this->authority = new Authority($authorityUrl, $validateAuthority);
  $this->cache = $cache;

  // Generate a correlation ID
  $this->correlationId = UUID::generate(4);

  // Set logging ID
  Logger::setLogId($this->correlationId);

  // Default debug to off
  $this->debug = false;
 }

 // Validate a token request
 private function validateRequest() {
  $val = false;

  try {
   $val = $this->authority->validate();
  } catch (Exception $e) {
   return;
  }

  return $val;
 }

 /*
  * Accessors
  */
 public function setDebug($val) {
  $this->debug = $val;
 }

 public function getDebug() {
  return $this->debug;
 }

 public function getAuthority() {
  return $this->authority;
 }

 public function getCorrelationId() {
  return $this->correlationId;
 }

 public function getCache() {
  return $this->cache;
 }

 /*
  * Token acquisition
  */
 public function acquireToken($resource, $userId, $clientId) {
  try {
   StringValidation::validateStringParameter($resource, 'resource');
   StringValidation::validateStringParameter($clientId, 'clientId');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource);
   return $tokenRequest->getTokenFromCacheWithRefresh($userId);
  }
 }

 public function acquireTokenWithUsernamePassword($resource, $username, $password, $clientId) {
  try {
   StringValidation::validateStringParameter($resource, 'resource');
   StringValidation::validateStringParameter($username, 'username');
   StringValidation::validateStringParameter($password, 'password');
   StringValidation::validateStringParameter($clientId, 'clientId');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource);
   return $tokenRequest->getTokenWithUsernamePassword($username, $password);
  }
 }

 public function acquireTokenWithClientCredentials($resource, $clientId, $clientSecret) {
  try {
   StringValidation::validateStringParameter($resource, 'resource');
   StringValidation::validateStringParameter($clientId, 'clientId');
   StringValidation::validateStringParameter($clientSecret, 'clientSecret');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource);
   return $tokenRequest->getTokenWithClientCredentials($clientSecret);
  }
 }

 public function acquireTokenWithAuthorizationCode($authorizationCode, $redirectUri, $resource, $clientId, $clientSecret) {
  try {
   StringValidation::validateStringParameter($resource, 'resource');
   StringValidation::validateStringParameter($authorizationCode, 'authorizationCode');
   StringValidation::validateStringParameter($redirectUri, 'redirectUri');
   StringValidation::validateStringParameter($clientId, 'clientId');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource, $redirectUri);
   return $tokenRequest->getTokenWithAuthorizationCode($authorizationCode, $clientSecret);
  }
 }

 public function acquireTokenWithRefreshToken($refreshToken, $clientId, $clientSecret, $resource) {
  try {
   StringValidation::validateStringParameter($refreshToken, 'refreshToken');
   StringValidation::validateStringParameter($clientId, 'clientId');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource);
   return $tokenRequest->getTokenWithRefreshToken($refreshToken, $clientSecret);
  }
 }

 public function acquireTokenWithClientCertificate($resource, $clientId, $certificate, $thumbprint) {
  try {
   StringValidation::validateStringParameter($resource, 'resource');
   StringValidation::validateStringParameter($certificate, 'certificate');
   StringValidation::validateStringParameter($thumbprint, 'thumbprint');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource);
   return $tokenRequest->getTokenWithCertificate($certificate, $thumbprint);
  }
 }

 public function acquireUserCode($resource, $clientId, $language) {
  try {
   StringValidation::validateStringParameter($resource, 'resource');
   StringValidation::validateStringParameter($clientId, 'clientId');
  } catch (Exception $e) {
   return;
  }

  if ($this->validateRequest()) {
   $codeRequest = new CodeRequest($this, $clientId, $resource);
   return $codeRequest->getUserCodeInfo($language);
  }
 }

 public function acquireTokenWithDeviceCode($resource, $clientId, $userCodeInfo) {
  try {
   UserCodeValidation::validateUserCodeInfo($userCodeInfo);
  } catch (Exception $e) {
   throw new Exception($e->getMessage());
  }

  if ($this->validateRequest()) {
   $tokenRequest = new TokenRequest($this, $clientId, $resource);
// $this->tokenRequestWithUserCode[$userCodeInfo[Constants::constant('UserCodeResponseFields')['DEVICE_CODE']]] = $tokenRequest;
   return $tokenRequest->getTokenWithDeviceCode($userCodeInfo);
  }
 }

 public function cancelRequestToGetTokenWithDeviceCode($userCodeInfo) {
  try {
   UserCodeValidation::validateUserCodeInfo($userCodeInfo);
  } catch (Exception $e) {
   return;
  }

  if (!$this->tokenRequestWithUserCode || !isset($this->tokenRequestWithUserCode[$userCodeInfo[Constants::constant('UserCodeResponseFields')['DEVICE_CODE']]])) {
   Logger::log('No acquireTokenWithDeviceCodeRequest existed to be cancelled');
   return;
  }

  $tokenRequestToBeCancelled = $this->tokenRequestWithUserCode[$userCodeInfo[Constants::constant('UserCodeResponseFields')['DEVICE_CODE']]];
  $tokenRequestToBeCancelled->cancelTokenRequestWithDeviceCode();

  unset($this->tokenRequestWithUserCode[Constants::constant('UserCodeResponseFields')['DEVICE_CODE']]);
 }
}
?>
