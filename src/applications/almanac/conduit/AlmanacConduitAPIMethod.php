<?php

abstract class AlmanacConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorAlmanacApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht(
      'Almanac is a prototype application and its APIs are '.
      'subject to change.');
  }

  protected function getServiceDictionary(AlmanacService $service) {
    return array(
      'id' => (int)$service->getID(),
      'phid' => $service->getPHID(),
      'name' => $service->getName(),
      'uri' => PhabricatorEnv::getProductionURI($service->getURI()),
      'serviceClass' => $service->getServiceClass(),
      'properties' => $this->getPropertiesDictionary($service),
    );
  }

  protected function getBindingDictionary(AlmanacBinding $binding) {
    return array(
      'id' => (int)$binding->getID(),
      'phid' => $binding->getPHID(),
      'properties' => $this->getPropertiesDictionary($binding),
      'interface' => $this->getInterfaceDictionary($binding->getInterface()),
    );
  }

  protected function getPropertiesDictionary(AlmanacPropertyInterface $obj) {
    $properties = $obj->getAlmanacProperties();
    return (object)mpull($properties, 'getFieldValue', 'getFieldName');
  }

  protected function getInterfaceDictionary(AlmanacInterface $interface) {
    return array(
      'id' => (int)$interface->getID(),
      'phid' => $interface->getPHID(),
      'address' => $interface->getAddress(),
      'port' => (int)$interface->getPort(),
      'device' => $this->getDeviceDictionary($interface->getDevice()),
      'network' => $this->getNetworkDictionary($interface->getNetwork()),
    );
  }

  protected function getDeviceDictionary(AlmanacDevice $device) {
    return array(
      'id' => (int)$device->getID(),
      'phid' => $device->getPHID(),
      'name' => $device->getName(),
      'properties' => $this->getPropertiesDictionary($device),
    );
  }

  protected function getNetworkDictionary(AlmanacNetwork $network) {
    return array(
      'id' => (int)$network->getID(),
      'phid' => $network->getPHID(),
      'name' => $network->getName(),
    );
  }

}
