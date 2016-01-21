<?php
namespace phpADAL;

use Authentication\AuthenticationContext;
use Log\Logger;

class Adal {
 private static $authenticationContext;

 static function createAuthorizationContext($authority, $validateAuthority) {
  self::$authenticationContext = new AuthenticationContext($authority, $validateAuthority);

  return self::$authenticationContext;
 }
}
?>
