<?php
namespace phpADAL\Cache\Drivers;

abstract class AbstractCacheDriver {
 protected $entries;

 function __construct() {
  $this->entries = array();
 }

 /*
  * copy()
  * Copies cache data from one driver to another, destroying the targets cache entries
  */
 public function copy($entries) {
  $this->entries = $entries;
 }

 public function getAll() {
  return $this->entries;
 }

 public function remove($entries) {
  foreach($entries as $entry) {
   if (isset($this->entries[$entry])) {
    unset($this->entries[$entry]);
   }
  }
  return;
 }

 public function add($entries) {
  // Remove existing duplicate entries
  foreach($this->entries as $existingEntry) {
   foreach($entries as $entry) {
    if ($existingEntry === $entry) {
     unset($entries[$entry]);
    }
   }
  }

  // Append the entries to the cache list
  foreach($entries as $entry) {
   $this->entries[] = $entry;
  }
 }

 public function find($query) {
  $entries = null;

  foreach($query as $queryItem) {
   if (isset($this->entries[$queryItem])) {
    $entries[] = $this->entries[$queryItem];
   }
  }

  return $entries;
 }
}
?>
