<?php
namespace phpADAL\Request;

use phpADAL\Request\OAuth2;
use phpADAL\Request\UserRealm;
use phpADAL\Cache\CacheManager;
use phpADAL\Constants;
use phpADAL\Log\Logger;

class Token {
 protected $context;
 protected $resource;
 protected $clientId;
 protected $userId;
 protected $realm;
 protected $redirectUri;
 protected $oauth;
 protected $cache;

 function __construct($context, $clientId, $resource, $redirectUri=null) {
  $this->context = $context;
  $this->resource = $resource;
  $this->clientId = $clientId;
  $this->redirectUri = $redirectUri;

  $this->oauth = new OAuth2($context, $context->getAuthority());
  $this->cache = new CacheManager();
  $this->cache->setAuthority($this->context->getAuthority());
  $this->cache->setClientId($this->clientId);
 }

 private function createUserRealmRequest($username) {
  return new UserRealm($this->context, $username);
 }

 private function getTokenUsernamePasswordManaged($username, $password) {
  Logger::log('Acquiring token with username password for managed user');

  $params = $this->createParameters(Constants::constant('OAuth2')['GrantType']['PASSWORD']);
  $params[Constants::constant('OAuth2')['Parameters']['USERNAME']] = $username;
  $params[Constants::constant('OAuth2')['Parameters']['PASSWORD']] = $password;

  Logger::log('Created Token Parameters: ', $params);

  return $this->oauth->getToken($params);
 }

 private function getTokenUsernamePasswordFederated($username, $password) {
 }

 private function getTokenFromCache() {
  $cacheQuery = array(
   'clientId' => $this->clientId
  );

  if ($this->userId) {
   $cacheQuery['userId'] = $this->userId;
  }

  return $this->cache->find($cacheQuery);
 }

 private function createParameters($grantType) {
  $params = array();
  $params[Constants::constant('OAuth2')['Parameters']['GRANT_TYPE']] = $grantType;

  if (Constants::constant('OAuth2')['GrantType']['AUTHORIZATION_CODE'] !== $grantType ||
      Constants::constant('OAuth2')['GrantType']['CLIENT_CREDENTIALS'] !== $grantType ||
      Constants::constant('OAuth2')['GrantType']['REFRESH_TOKEN'] !== $grantType ||
      Constants::constant('OAuth2')['GrantType']['DEVICE_CODE'] !== $grantType)
  {
   $params[Constants::constant('OAuth2')['Parameters']['SCOPE']] = Constants::constant('OAuth2')['Scope']['OPENID'];
  }

  if ($this->clientId) {
   $params[Constants::constant('OAuth2')['Parameters']['CLIENT_ID']] = $this->clientId;
  }

  if ($this->resource) {
   $params[Constants::constant('OAuth2')['Parameters']['RESOURCE']] = $this->resource;
  }

  if ($this->redirectUri) {
   $params[Constants::constant('OAuth2')['Parameters']['REDIRECT_URI']] = $this->redirectUri;
  }

  return $params;
 }

 public function getTokenWithUsernamePassword($username, $password) {
  $this->userId = $username;
  $this->realm = $this->createUserRealmRequest($username);
  $token = null;

  try {
   $this->realm->discover();
  } catch (Exception $e) {
   throw new Exception();
  }

  switch($this->realm->getAccountType()) {
   case 'managed':
    $token = $this->getTokenUsernamePasswordManaged($username, $password);
    break;
   case 'federated':
    $token = $this->getTokenUsernamePasswordFederated($username, $password);
    break;
   default:
    Logger::log('Server returned an unknown AccountType: ' . $this->realm->getAccountType());
    break;
  }

  return $token;
 }

 public function getTokenWithClientCredentials($clientSecret) {
  Logger::log('Getting a token via client credentials');

  $token = $this->getTokenFromCache();

  if (!$token) {
   Logger::log('No appropriate cached token found.');

   $params = $this->createParameters(Constants::constant('OAuth2')['GrantType']['CLIENT_CREDENTIALS']);
   $params[Constants::constant('OAuth2')['Parameters']['CLIENT_SECRET']] = $clientSecret;

   Logger::log('Created Token Parameters: ', $params);

   $token = $this->oauth->getToken($params);
   if ($token) {
    Logger::log('Successfully retrieved token from authority');
    $this->cache->add($token);
   }
  } else {
   Logger::log('Returning cached token.');
  }

  return $token;
 }

 public function getTokenWithDeviceCode($userCodeInfo) {
  Logger::log('Getting a token via device code');

  $token = $this->getTokenFromCache();

  if (!$token) {
   $params = $this->createParameters(Constants::constant('OAuth2')['GrantType']['DEVICE_CODE']);
   $params[Constants::constant('OAuth2')['Parameters']['CODE']] = $userCodeInfo[Constants::constant('UserCodeResponseFields')['DEVICE_CODE']]; //Constants::constant('OAuth2')['DeviceCodeResponseParameters']['DEVICE_CODE']];

   Logger::log('Created Token Parameters: ', $params);

   $token = $this->oauth->getToken($params);

   if ($token) {
    $this->cache->add($token);
   }
  }

  return $token;
 }
}
?>
