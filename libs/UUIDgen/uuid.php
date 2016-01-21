<?php
/*
 * PHP Port of node-uuid by Robert Kieffer.
 *
 * Copyright (c) 2015 Chris Houlden.
 */

namespace UUIDgen;

class UUID {
 // Default constructor
 function __construct() {
 }

 // Generate a UUID based on version number
 static function generate($version) {
  if (is_int($version)) {
   $st = new static;

   switch ($version) {
    case '4':
     return $st->genV4();
    default:
     throw new Exception('Invalid version');
   }
  }
 }

 // Generate a version 4 UUID
 private function genV4() {
  $rand = $this->rand(16);

  $rand[6] = chr(ord($rand[6]) & 0x0f | 0x0f);
  $rand[8] = chr(ord($rand[8]) & 0x3f | 0x80);

  // Return the string
  return $this->byteToString($rand);
 }

 // Generates a random number of specified length
 private function rand($length) {
  if (function_exists('random_bytes')) {
   return call_user_func('random_bytes', $length);
  } else if (function_exists('openssl_random_pseudo_bytes')) {
   return call_user_func('openssl_random_pseudo_bytes', $length);
  } else if (function_exists('mcrypt_encrypt')) {
   return call_user_func('mcrypt_create_iv', $length, MCRYPT_DEV_URANDOM);
  }
 }

 // Convert the byte array to the correctly formatted string
 private function byteToString($buffer, $offset=null) {
  return (bin2hex(substr($buffer, 0, 4)) . '-' .
          bin2hex(substr($buffer, 4, 2)) . '-' .
          bin2hex(substr($buffer, 8, 2)) . '-' .
          bin2hex(substr($buffer, 10, 6))
  );
 }
}
?>
