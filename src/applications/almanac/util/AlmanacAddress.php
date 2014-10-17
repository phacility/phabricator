<?php

final class AlmanacAddress extends Phobject {

  private $networkPHID;
  private $address;
  private $port;

  private function __construct() {
    // <private>
  }

  public function getNetworkPHID() {
    return $this->networkPHID;
  }

  public function getAddress() {
    return $this->address;
  }

  public function getPort() {
    return $this->port;
  }

  public static function newFromDictionary(array $dictionary) {
    return self::newFromParts(
      $dictionary['networkPHID'],
      $dictionary['address'],
      $dictionary['port']);
  }

  public static function newFromParts($network_phid, $address, $port) {
    $addr = new AlmanacAddress();

    $addr->networkPHID = $network_phid;
    $addr->address = $address;
    $addr->port = (int)$port;

    return $addr;
  }

  public function toDictionary() {
    return array(
      'networkPHID' => $this->getNetworkPHID(),
      'address' => $this->getAddress(),
      'port' => $this->getPort(),
    );
  }

  public function toHash() {
    return PhabricatorHash::digestForIndex(json_encode($this->toDictionary()));
  }

}
