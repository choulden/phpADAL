<?php
namespace phpADAL\Cache;

use phpADAL\Constants;
use phpADAL\Cache\DriverManager;
use phpADAL\Cache\CacheEntry;
use phpADAL\Log\Logger;

define('METADATA_CLIENTID', '_clientId');
define('METADATA_AUTHORITY', '_authority');

class CacheManager {
 protected $driverManager;
 protected $cacheDriver;

 protected $hashAlg;

 protected $authority;
 protected $resource;
 protected $clientId;
/*
 function __construct($authority, $resource, $clientId) {
  $this->hashAlg = Constants::constant('Cache')['HASH_ALGORITHM'];

  $this->authority = $authority;
  $this->resource = $resource;
  $this->clientId = $clientId;
*/
 function __construct($driver=null) {
  // Create a new driver manager
  $this->driverManager = new DriverManager(Constants::constant('Cache')['CACHE_DRIVERS']);

  // Retrieve our cache driver
  $this->cacheDriver = $this->driverManager->driver($driver);
 }

 public function setAuthority($authority) {
  $this->authority = $authority;
 }

 public function setClientId($clientId) {
  $this->clientId = $clientId;
 }

 // Creates a token hash using a base64 encoded sha256 algorithm
 public function createTokenHash($token) {
  $hash = hash(Constants::constant('Cache')['HASH_ALGORITHM'], $token);
  return base64_encode($hash);
 }

 // Creates a readable message for the token & refresh tokens
 public function createTokenIdMessage($entry) {
  $accessTokenHash = $this->createTokenHash($entry[Constants::constant('TokenResponseFields')['ACCESS_TOKEN']]);
  $message = 'AccessTokenId: ' . $accessTokenHash;

  if (isset($entry[Constants::constant('TokenResponseFields')['REFRESH_TOKEN']])) {
   $refreshTokenHash = $this->createTokenHash($entry[Constants::constant('TokenResponseFields')['REFRESH_TOKEN']]);
   $message .= ', RefreshTokenId: ' . $refreshTokenHash;
  }

  return $message;
 }

 // Returns if the entry is a multi resource refresh token
 private function isMRRT($entry) {
  return $entry['resource'] ? true : false;
 }

 // Checks if an entry already has metadata
 private function entryHasMetadata($entry) {
  return (isset($entry[METADATA_CLIENTID]) && isset($entry[METADATA_AUTHORITY]));
 }

 // Add metadata to an entry
 private function augmentEntryWithCacheMetadata($entry) {
  // Skip if this entry already has metadata
  if ($this->entryHasMetadata($entry)) {
   return;
  }

  if ($this->isMRRT($entry)) {
   Logger::log('Added cache entry is MRRT');
   $entry['isMRRT'] = true;
  } else {
   $entry['resource'] = $this->resource;
  }

  $entry[METADATA_CLIENTID] = $this->clientId;
  $entry[METADATA_AUTHORITY] = $this->authority;

  return $entry;
 }

 // Execute a query for all MRRT Tokens for the specified userId
 private function findMRRTTokensForUser($userId) {
  $query = $this->find(array(
    'isMRRT' => true,
    'userId' => $userId,
    'clientId' => $this->clientId
   )
  );

  return $query;
 }

 // Update refresh tokens for this entry
 private function updateRefreshTokens($entry) {
  if ($this->isMRRT($entry)) {
   if (array_key_exists('userId', $entry)) {
    $tokens = $this->findMRRTTokensForUser($entry['userId']);
    if (!$tokens || !isset($tokens) || sizeof($tokens) <= 0) {
     return;
    }

    Logger::log('Updating ' . sizeof($tokens) . ' cached refresh tokens.');
    if ($this->removeMany($tokens)) {
     foreach ($tokens as $token) {
      $token[Constants::constant('TokenResponseFields')['REFRESH_TOKEN']] = $entry[Constants::constant('TokenResponseFields')['REFRESH_TOKEN']];
     }

     $this->addMany($tokens);

     return true;
    }
   }
  }

  return; 
 }

 // Finds a single entry in the cache
 private function loadSingleEntryFromCache($query) {
  $entries = $this->getPotentialEntries($query);

  $returnVal = null;
  $isResourceTenantSpecific = false;

  if ($entries && sizeof($entries) > 0) {
   $resourceTenantSpecificEntries = $this->getTenantSpecificEntries($entries);

   if (!$resourceTenantSpecificEntries || sizeof($resourceTenantSpecificEntries) <= 0) {
    Logger::log('No resource specific cache entries found.');

    // There are no resource specific entries.  Find an MRRT token.
    $mrrtTokens = $this->getMRRTTokens($entries);

    if ($mrrtTokens && sizeof($mrrtTokens) > 0) {
     Logger::log('Found an MRRT token.');
     $returnVal = new CacheEntry($mrrtTokens[0]);
    } else {
     Logger::log('No MRRT tokens found.');
    }
   } else if (sizeof($resourceTenantSpecificEntries) === 1) {
    Logger::log('Resource specific token found.');

    $returnVal = new CacheEntry($resourceTenantSpecificEntries[0], true);
   } else {
    Logger::log('More than one token matches the criteria.  The result is ambiguous.');
    return false;
   }
  }

  if ($returnVal) {
   Logger::log('Returning token from cache lookup, ' . $this->createTokenIdMessage($returnVal));
  }

  return $returnVal;
 }

 private function getPotentialEntries($query) {
  $potentialEntries = array();

  if (array_key_exists(METADATA_CLIENTID, $query) && sizeof($query[METADATA_CLIENTID]) > 0) {
   $potentialEntries[METADATA_CLIENTID] = $query['clientId'];
  }

  if (array_key_exists(Constants::constant('TokenResponseFields')['USER_ID'], $query) && sizeof($query[Constants::constant('TokenResponseFields')['USER_ID']]) > 0) {
   $potentialEntries[Constants::constant('TokenResponseFields')['USER_ID']] = $query['userId'];
  }

  Logger::log('Looking for potential cache entries:', $potentialEntries);

  $entries = $this->cacheDriver->find($potentialEntries);

  if ($entries && sizeof($entries) > 0) {
   Logger::log('Found ' . sizeof($entries) . ' potential entries.');
   return $entries;
  }

  return false; // default return
 }

 /*
  * Cache functions
  */

 // Find an entry in the cache
 public function find($query) {
  Logger::log('Finding cache entry with query:', $query);

  $entry = $this->loadSingleEntryFromCache($query);
  if ($entry) {
   return $this->refreshEntryIfNecessary($entry->getToken(), $entry->isResourceTenantSpecific());
  }

  return;
 }

 // Remove an entry from the cache
 public function remove($entry) {
  Logger::log('Removing cache entry, ' . $this->createTokenIdMessage($entry));

  return $this->cacheDriver->remove($entry);
 }

 // Add an entry to the cache
 public function add($entry) {
  Logger::log('Adding cache entry, ' . $this->createTokenIdMessage($entry));

  $entry = $this->augmentEntryWithCacheMetadata($entry);
  $refreshTokens = null;

  try {
   $refreshTokens = $this->updateRefreshTokens($entry);
  } catch (Exception $e) {
   Logger::log('Error update refresh token');
   return;
  }

  if (!$refreshTokens) {
   $this->cacheDriver->add(array($entry));
  }

  Logger::log('Cache Entries:', $this->cacheDriver->getAll());
  return;
 }
}
?>
