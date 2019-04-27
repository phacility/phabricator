<?php

abstract class PhabricatorDashboardPanelPropertyTransaction
  extends PhabricatorDashboardPanelTransactionType {

  abstract protected function getPropertyKey();

  public function generateOldValue($object) {
    $property_key = $this->getPropertyKey();
    return $object->getProperty($property_key);
  }

  public function applyInternalEffects($object, $value) {
    $property_key = $this->getPropertyKey();
    $object->setProperty($property_key, $value);
  }

}
