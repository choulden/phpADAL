<?php
namespace phpADAL\Cache;

class CacheEntry {
 protected $token;
 protected $isResourceTenantSpecific;

 function __construct($token, $isResourceTenantSpecific=false) {
  $this->token = $token;
  $this->isResourceTenantSpecific = $isResourceTenantSpecific;
 }

 public function getToken() {
  return $this->token;
 }

 public function isResourceTenantSpecific() {
  return $this->isResourceTenantSpecific;
 }
}
?>
