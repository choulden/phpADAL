<?php
namespace phpADAL\HTTP;

class Response {
 protected $responseCode;
 protected $responseBody;

 public function getResponseCode() {
  return $this->responseCode;
 }

 public function getResponseBody() {
  return $this->responseBody;
 }

 public function __set($name, $value) {
  $this->$name = $value;
 }
}
?>
