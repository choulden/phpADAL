<?php
namespace phpADAL\Log;

use \DateTime;

class Logger {
 protected static $messages;
 protected static $logId;

 public function __construct() {
  $this->messages = array();
 }

 // Internal logging method
 private function _log($message, $return_entry=false, $sub_entry=null) {
  $entry = array(
   'timestamp' => date("Y-m-d H:i:s"),
   'message' => $message
  );

  // Add the sub-entries if they exist
  if ($sub_entry && is_array($sub_entry)) {
   $entry['sub_entries'] = $sub_entry;
  }

  // Return the entry array, or append to the main stack
  if ($return_entry == true) {
   return $entry;
  } else {
   self::$messages[] = $entry;
  }
 }

 public static function setLogId($id) {
  self::$logId = $id;
 }

 protected function createMessage($key, $value) {
  $msg = '';

  if (is_int($key)) {
   $msg = $value;
  } else {
   if (!is_object($key) && !is_object($value)) {
    $msg = $key . " : " . $value;
   }
  }

  return $msg;
 }

 // Public logging function
 public static function log($message, $vars=null) {
  if ($vars && is_array($vars)) {
   $sub_entries = array();

   // Create each entry as a sub-entry
   foreach($vars as $key => $value) {
    if (is_array($value)) {
     foreach($value as $k => $v) {
      $msg = self::createMessage($k, $v);
      $sub_entries[] = self::_log($msg, true);
     }
    } else {
     $msg = self::createMessage($key, $value);
     $sub_entries[] = self::_log($msg, true);
    }
   }

   // Log the master entry
   self::_log($message, false, $sub_entries);
  } else if ($vars && !is_array($vars)) {
   $sub_entry[] = self::_log($vars, true);
   self::_log($message, false, $sub_entry);
  } else {
   self::_log($message);
  }
 }

 public static function display() {
  echo "Logging instance ID: " . self::$logId . "\n";

  foreach(self::$messages as $message) {
   echo $message['timestamp'] . ": " . $message['message'] . "\n";
   if (array_key_exists('sub_entries', $message) && is_array($message['sub_entries'])) {
    foreach($message['sub_entries'] as $sub_message) {
     if ($sub_message['message'] instanceof DateTime) {
      echo "   " . $sub_message['message']->format('Y-m-d H:i:s') . "\n";
     } else if (is_a($sub_message['message'], 'Authority')) {
     } else {
      echo "   " . $sub_message['message'] . "\n";
     }
    }
   }
  }
 }
}
?>
