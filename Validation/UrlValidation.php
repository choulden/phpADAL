<?php
namespace phpADAL\Validation;

define("DEFAULT_VALIDATION_FAILURE", "The rule failed validation");


class UrlValidation extends Validation {
 protected $rules = array();

 function addRule($field, $expectedValue, $pattern, $message=null) {
  $rule = array(
   'expectedValue' => $expectedValue,
   'pattern' => $pattern,
   'message' => $message ? $message : DEFAULT_VALIDATION_FAILURE
  );

  $this->rules[$field] = $rule;
 }

 function deleteRule($field) {
  unset($rules[$field]);
 }

 /*
  * Validates a given parsed URL array
  * @public
  * @param {array}  URL array that has been passed through parse_url()
  * @return {bool}
  */
 function validate($url) {
  if (!$this->rules || sizeof($this->rules) <= 0) {
   throw new \Exception('No validation rules were specified.');
  }
  if (!$url) {
   throw new \Exception('No URL was specified.');
  }
  if (!is_array($url)) {
   throw new \Exception('URL has not been parsed through parse_url().');
  }

  // Process each rule
  foreach ($this->rules as $rule => $key) {
   $value = isset($url[$rule]) ? $url[$rule] : null;
   if (!$value) {
    continue;
   }

   $expectedValue = $key['expectedValue'];

   $expr = 'if ($a ' . $key['pattern'] . ' $b) return true; else return false;';
   $f = create_function('$a, $b', $expr);

   if ($f($value, $expectedValue) == false) {
    throw new \Exception($key['message']);
   }
  }
 }
}
?>
