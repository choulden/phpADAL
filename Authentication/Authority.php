<?php
namespace phpADAL\Authentication;

use phpADAL\Constants as Constants;
use phpADAL\Exception\ADALException as Exception;
use phpADAL\Log\Logger;
use phpADAL\Validation\UrlValidation;
use phpADAL\HTTP\Request as HTTPRequest;

class Authority {
 protected $url;

 protected $validated;
 protected $host;
 protected $tenant;
 protected $authorizationEndpoint;
 protected $tokenEndpoint;
 protected $deviceCodeEndpoint;

 function __construct($authorityUrl, $validateAuthority) {
  $this->url = parse_url($authorityUrl);
  $this->validated = !$validateAuthority;

  // Validate the Authority URL
  $this->validateAuthorityUrl();

  $this->host = null;
  $this->tenant = null;
  $this->authorizationEndpoint = null;
  $this->tokenEndpoint = null;
  $this->deviceCodeEndpoint = null;

  $this->parseAuthority();
 }

 private function parseAuthority() {
  $this->host = $this->url['host'];

  // Explode the URL Path
  $path_a = explode('/', $this->url['path']);

  $this->tenant = $path_a[1];

  if (!$this->tenant) {
   throw new Exception('Unable to determine tenant.');
  }
 }

 // Validate the Authority URL
 private function validateAuthorityUrl() {
  $uv = new UrlValidation();
  $uv->addRule('scheme', 'https', '==');
  $uv->addRule('query', '', '==');

  try {
   $uv->validate($this->url);
  } catch (Exception $e) {
   Logger::log($e->getMessage());
  }
 }

 // Create an Authority URL from components
 private function createAuthorityUrl() {
  return 'https://' . $this->url['host'] . '/' . $this->tenant . Constants::constant('AADConstants')['AUTHORIZE_ENDPOINT_PATH'];
 }

 private function performStaticInstanceDiscovery() {
  $hostIndex = array_search($this->url['host'], Constants::constant('AADConstants')['WELL_KNOWN_AUTHORITY_HOSTS']);

  if ($hostIndex >= 0) {
   Logger::log('Authority validated via Static Instance Discovery');
   return true;
  }

  return false;	// Default return
 }

 private function createInstanceDiscoveryEndpointFromTemplate($authorityHost) {
  $discoveryEndpoint = Constants::contant('AADConstants')['INSTANCE_DISCOVERY_ENDPOINT_TEMPLATE'];
  $discoveryEndpoint = str_replace('{authorize_host}', $authorityHost, $discoveryEndpoint);
  $discoveryEndpoint = str_replace('{authorize_endpoint}', $this->createAuthorityUrl(), $discoveryEndpoint);

  return $discoveryEndpoint;
 }

 private function performDynamicInstanceDiscovery() {
  $discoveryEndpoint = $this->createInstanceDiscoveryEndpointFromTemplate(Constants::constant('AADConstants')['WORLD_WIDE_AUTHORITY']);

  $request = new HTTPRequest();
  $request->createRequestOptions();
  $request->setURL($discoveryEndpoint);

  $response = $request->get();	// URL, withOptions
  $response = json_decode($response->getResponseBody());

  if ($response['tenant_discovery_endpoint']) {
   Logger::log('Authority validated via Dynamic Instance Discovery');
   return $response['tenant_discovery_endpoint'];
  } else {
   return false;
  }
 }

 private function validateViaInstanceDiscovery() {
  $discovery = $this->performStaticInstanceDiscovery();
  if (!$discovery) {
   $discovery = $this->performDynamicInstanceDiscovery();
  }

  return $discovery;
 }

 private function getOAuthEndpoints($tenantDiscoveryEndpoint) {
  if (!$this->tokenEndpoint) {
   $this->tokenEndpoint = 'https://' . $this->url['host'] . '/' . $this->tenant . Constants::constant('AADConstants')['TOKEN_ENDPOINT_PATH'];
  }
  if (!$this->deviceCodeEndpoint) {
   $this->deviceCodeEndpoint = 'https://' . $this->url['host'] . '/' . $this->tenant . Constants::constant('AADConstants')['DEVICE_ENDPOINT_PATH'];
  }

  Logger::log('Token endpoint path: ' . $this->tokenEndpoint);
  Logger::log('DeviceCode endpoint path: ' . $this->deviceCodeEndpoint);

  return;
 }

 function validate() {
  if (!$this->validated) {
   Logger::log('Performing instance discovery', $this->url);

   $inst = $this->validateViaInstanceDiscovery();
   if (!$inst) {
    throw new Exception('Failed validation of Authority.');
   } else {
    $this->validated = true;
    $this->getOAuthEndpoints($inst);
    return true;
   }
  } else {
   Logger::log('Instance discovery/validation has either already been completed or is turned off', $this->url);

   $this->getOAuthEndpoints(null);

   return true;
  }
 }

 /*
  * Accessors
  */
 public function getTokenEndpoint() {
  return $this->tokenEndpoint;
 }

 public function getDeviceCodeEndpoint() {
  return $this->deviceCodeEndpoint;
 }

 public function getUrl() {
  return $this->url;
 }
}
?>
