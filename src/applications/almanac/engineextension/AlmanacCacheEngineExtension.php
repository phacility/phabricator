<?php

final class AlmanacCacheEngineExtension
  extends PhabricatorCacheEngineExtension {

  const EXTENSIONKEY = 'almanac';

  public function getExtensionName() {
    return pht('Almanac Core Objects');
  }

  public function discoverLinkedObjects(
    PhabricatorCacheEngine $engine,
    array $objects) {
    $viewer = $engine->getViewer();

    $results = array();
    foreach ($this->selectObjects($objects, 'AlmanacBinding') as $object) {
      $results[] = $object->getServicePHID();
      $results[] = $object->getDevicePHID();
      $results[] = $object->getInterfacePHID();
    }

    $devices = $this->selectObjects($objects, 'AlmanacDevice');
    if ($devices) {
      $interfaces = id(new AlmanacInterfaceQuery())
        ->setViewer($viewer)
        ->withDevicePHIDs(mpull($devices, 'getPHID'))
        ->execute();
      foreach ($interfaces as $interface) {
        $results[] = $interface;
      }

      $bindings = id(new AlmanacBindingQuery())
        ->setViewer($viewer)
        ->withDevicePHIDs(mpull($devices, 'getPHID'))
        ->execute();
      foreach ($bindings as $binding) {
        $results[] = $binding;
      }
    }

    foreach ($this->selectObjects($objects, 'AlmanacInterface') as $iface) {
      $results[] = $iface->getDevicePHID();
      $results[] = $iface->getNetworkPHID();
    }

    foreach ($this->selectObjects($objects, 'AlmanacProperty') as $object) {
      $results[] = $object->getObjectPHID();
    }

    return $results;
  }

  public function deleteCaches(
    PhabricatorCacheEngine $engine,
    array $objects) {
    return;
  }

}
