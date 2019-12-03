<?php

final class DiffusionServiceRef
  extends Phobject {

  private $uri;
  private $protocol;
  private $isWritable;
  private $devicePHID;
  private $deviceName;

  private function __construct() {
    return;
  }

  public static function newFromDictionary(array $map) {
    $ref = new self();

    $ref->uri = $map['uri'];
    $ref->isWritable = $map['writable'];
    $ref->devicePHID = $map['devicePHID'];
    $ref->protocol = $map['protocol'];
    $ref->deviceName = $map['device'];

    return $ref;
  }

  public function isWritable() {
    return $this->isWritable;
  }

  public function getDevicePHID() {
    return $this->devicePHID;
  }

  public function getURI() {
    return $this->uri;
  }

  public function getProtocol() {
    return $this->protocol;
  }

  public function getDeviceName() {
    return $this->deviceName;
  }

}
