<?php
namespace phpADAL\Request;

use phpADAL\Request\OAuth2;
use phpADAL\Constants;
use phpADAL\Log\Logger;

class Code {
 protected $context;
 protected $resource;
 protected $clientId;
 protected $userId;
 protected $oauth;

 function __construct($context, $clientId, $resource) {
  $this->context = $context;
  $this->resource = $resource;
  $this->clientId = $clientId;

  $this->userId = null;

  $this->oauth = new OAuth2($context, $context->getAuthority());
 }

 protected function _getOAuthParameters() {
  return array(
   Constants::constant('OAuth2')['Parameters']['CLIENT_ID'] => $this->clientId,
   Constants::constant('OAuth2')['Parameters']['RESOURCE'] = $this->resource
  );
 }

 public function getUserCodeInfo($language) {
  Logger::log('Getting user code info.');

  $params = $this->_getOAuthParameters();
  if ($language) {
   $params[Constants::constant('OAuth2')['Parameters']['LANGUAGE']] = $language;
  }

  return $this->oauth->getUserCodeInfo($params);
 }
}
?>
