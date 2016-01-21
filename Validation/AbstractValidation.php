<?php
namespace phpADAL\Validation;

use phpADAL\Constants;

abstract class Validation
{
 protected $userCodeResponseFields;

 function __construct()
 {
  $this->userCodeResponseFields = Constants::constant('UserCodeResponseFields');
 }

 function contains($arr, $value) {
  if (array_search($value, array_values($arr))) {
   return true;
  } else {
   return false;
  }
 }
}
?>
