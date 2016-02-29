<?php

abstract class AlmanacSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  protected function getAlmanacPropertyList($object) {
    $builtins = $object->getAlmanacPropertyFieldSpecifications();

    $properties = array();
    foreach ($object->getAlmanacProperties() as $key => $property) {
      $is_builtin = isset($builtins[$key]);

      $properties[] = array(
        'key' => $key,
        'value' => $property->getFieldValue(),
        'builtin' => $is_builtin,
      );
    }

    return $properties;
  }

  protected function getAlmanacBindingDictionary(AlmanacBinding $binding) {
    $interface = $binding->getInterface();

    return array(
      'id' => (int)$binding->getID(),
      'phid' => $binding->getPHID(),
      'properties' => $this->getAlmanacPropertyList($binding),
      'interface' => $this->getAlmanacInterfaceDictionary($interface),
      'disabled' => (bool)$binding->getIsDisabled(),
    );
  }

  protected function getAlmanacInterfaceDictionary(
    AlmanacInterface $interface) {

    return array(
      'id' => (int)$interface->getID(),
      'phid' => $interface->getPHID(),
      'address' => $interface->getAddress(),
      'port' => (int)$interface->getPort(),
      'device' => $this->getAlmanacDeviceDictionary($interface->getDevice()),
      'network' => $this->getAlmanacNetworkDictionary($interface->getNetwork()),
    );
  }

  protected function getAlmanacDeviceDictionary(AlmanacDevice $device) {
    return array(
      'id' => (int)$device->getID(),
      'phid' => $device->getPHID(),
      'name' => $device->getName(),
      'properties' => $this->getAlmanacPropertyList($device),
    );
  }

  protected function getAlmanacNetworkDictionary(AlmanacNetwork $network) {
    return array(
      'id' => (int)$network->getID(),
      'phid' => $network->getPHID(),
      'name' => $network->getName(),
    );
  }

}
