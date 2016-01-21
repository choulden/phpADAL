<?php
namespace phpADAL\HTTP;

use phpADAL\Constants;
use phpADAL\Log\Logger;
use phpADAL\HTTP\Response as HTTPResponse;

class Request {
 protected $url;
 protected $username;
 protected $password;

 protected $requireAuthentication;

 protected $request;
 protected $headers;

 protected $debug;

 protected $context;
 protected $data;

 protected $responseBody;
 protected $responseCode;

 public function __construct($context=null) {
  $this->request = curl_init();
  $this->headers = array();
  $this->context = $context;

  // Set defaults
  $this->requireAuthentication = false;
  $this->debug = $context->getDebug();

  // Add default headers
  $this->addDefaultHeaders();
 }

 private function _sendRequest() {
  Logger::log('HTTP\Request\_sendRequest()', $this->url);
  if ($this->data) {
   Logger::log('HTTP\Request\_sendRequest()', $this->data);
  }

  // Set the URL for the request
  curl_setopt($this->request, CURLOPT_URL, $this->buildURL($this->url));

  // Require value to be returned
  curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);

  // Add headers
  curl_setopt($this->request, CURLOPT_HTTPHEADER, $this->headers);

  // Set debugging
  if ($this->debug == true) {
   curl_setopt($this->request, CURLINFO_HEADER_OUT, true);
  }

  // Add authentication if required
  if ($this->requireAuthentication) {
   curl_setopt($this->request, CURLOPT_USERPWD, $this->username.':'.$this->password);
  }

  $response = new HTTPResponse();

  // Execute the request and retrieved the response
  $response->responseBody = curl_exec($this->request);

  // Retrieve the response code from the request
  $response->responseCode = curl_getinfo($this->request, CURLINFO_HTTP_CODE);

  // Close the connection
  curl_close($this->request);

  // Return the response
  return $response;
 }

 public function get() {
  // Complete the request
  return $this->_sendRequest();
 }

 public function post() {
  // Set POST
  curl_setopt($this->request, CURLOPT_POST, true);

  // Set POST data
  curl_setopt($this->request, CURLOPT_POSTFIELDS, $this->data);

  // Complete the request
  return $this->_sendRequest();
 }

/*
 public function getResponse() {
  return $this->responseBody;
 }

 public function getResponseCode() {
  return $this->responseCode;
 }
*/

 /*
  * Internal header functions
  */
 private function addDefaultHeaders() {
  $this->headers['Accept-Charset'] = 'utf-8';
  $this->headers['client-request-id'] = $this->context->getCorrelationId();
  $this->headers['return-client-request-id'] = 'true';

  $this->headers['Content-length'] = strlen($this->data);

  $adalIdConstants = Constants::constant('AdalIdParameters');

  // ADAL Id headers
  $this->headers[$adalIdConstants['SKU']] = $adalIdConstants['NODE_SKU'];
  $this->headers[$adalIdConstants['VERSION']] = Constants::constant('PHPADAL_VERSION');
  $this->headers[$adalIdConstants['OS']] = php_uname("s");
  $this->headers[$adalIdConstants['CPU']] = php_uname("m");
 }

 // Creates a URL from an array of components
 private function buildURL($url) {
  if (is_array($url)) {
   $u = $url['scheme'] . '://' . $url['host'] . $url['path'];
   if ($url['query']) {
    $u .= '?' . $url['query'];
   }
  } else {
   $u = $url;
  }

  return $u;
 }

 /*
  * Set methods
  */
 public function setURL($url) {
  $this->url = $url;
 }

 public function setData($data) {
  $this->data = $data;
 }

 // Builds a string from an array of objects
 public function setDataWithBuilder($data) {
  if (!is_array($data)) {
   // If the data is not an array, assume it is correctly formatted string already
   $this->setData($data);
  } else {
   $query_str = '';
   $query_strArr = array();

   foreach($data as $key => $value) {
    $query_strArr[] = $key.'='.$value;
   }

   $query_str = implode($query_strArr, '&');

   $this->setData($query_str);
  }
 }

 public function setAuthentication($val) {
  $this->requireAuthentication = $val;
 }

 public function addHeaders($options) {
//   $this->addDefaultHeaders();

   $this->headers = array_merge($this->headers, $options);
 }

 public function isHttpSuccess($code) {
  return $code >= 200 && $code < 300;
 }

 public function processRequest($response, $message=null) {
  if (!$this->isHttpSuccess($response->getResponseCode())) {
   $error = $message .= ' request returned HTTP error: ' . $response->getResponseCode();
   if ($response->getResponseBody()) {
    $error .= ' and server response: ' . $response->getResponseBody();
   }

   try {
    $errorResponse = json_decode($response->getResponseBody());
    if ($errorResponse) {
     throw new \Exception($errorResponse->error_description);
    }
   } catch(Exception $e) {
   }

   Logger::log($error, (array)$errorResponse);
   return false;
  }

  return true;	// Default return
 }
}
?>
