<?php
namespace phpADAL\Validation;

use phpADAL\Constants;

class UserCodeValidation extends Validation
{
 function validateUserCodeInfo($userCodeInfo) {
  if (!$userCodeInfo || !isset($userCodeInfo) || !is_array($userCodeInfo)) {
   throw new \Exception('The userCodeInfo parameter is required.');
  }

  if (!array_key_exists(Constants::constant('UserCodeResponseFields')['DEVICE_CODE'], $userCodeInfo)) {
   throw new \Exception('The userCodeInfo is missing device_code.');
  }

  if (!array_key_exists(Constants::constant('UserCodeResponseFields')['INTERVAL'], $userCodeInfo)) {
   throw new \Exception('The userCodeInfo is missing interval.');
  }

  if (!array_key_exists(Constants::constant('UserCodeResponseFields')['EXPIRES_IN'], $userCodeInfo)) {
   throw new \Exception('The userCodeInfo is missing expires_in.');
  }
 }
}
?>
