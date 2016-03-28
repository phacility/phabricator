<?php

final class AlmanacDevicePropertyEditEngine
  extends AlmanacPropertyEditEngine {

  const ENGINECONST = 'almanac.device.property';

  protected function newObjectQuery() {
    return new AlmanacDeviceQuery();
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getObjectName() {
    return pht('Property');
  }

}
