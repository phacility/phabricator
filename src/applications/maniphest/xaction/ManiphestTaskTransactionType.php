<?php

abstract class ManiphestTaskTransactionType
  extends PhabricatorModularTransactionType {

  public function renderSubtypeName($value) {
    $object = $this->getObject();
    $map = $object->newEditEngineSubtypeMap();
    if (!isset($map[$value])) {
      return $value;
    }

    return $map[$value]->getName();
  }

}
