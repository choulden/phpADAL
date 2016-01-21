<?php
namespace phpADAL\Validation;

use Constants;

class ConstantValidation extends Validation
{
 public static function validate($constants, $value, $caseSensitive=false) {
  if (!$value) {
   return false;
  }

  if ($caseSensitive == false) {
   $value = strtolower($value);
  }

  return self::contains($constants, $value) ? $value : false;
 }
}
