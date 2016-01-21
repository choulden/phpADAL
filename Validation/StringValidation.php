<?php
namespace phpADAL\Validation;

class StringValidation extends Validation {
 function validateStringParameter($param, $name) {
  if (!$param) {
   throw new \Exception('Parameter required.');
  }
  if (!is_string($param)) {
   throw new \Exception('The parameter ' . $name . ' is not a String.');
  }
 }
}
?>
