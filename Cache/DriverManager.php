<?php
namespace phpADAL\Cache;

class DriverManager {
 protected $drivers = array();

 function __construct($drivers) {
  $this->create_driver($drivers);
 }

 public function driver($driver=null)
 {
  $driver = $driver ?: $this->getDefaultDriver();

  if (!isset($this->drivers[$driver]))
  {
   $this->create_driver($driver);
  }

  return $this->drivers[$driver];
 }

 public function getDefaultDriver()
 {
  return 'memory';
 }

 protected function create_driver($drivers)
 {
  foreach ($drivers as $name => $class)
  {
   $this->drivers[$name] = new $class;
  }
 }
}
?>
