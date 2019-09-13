<?php

final class PhabricatorEditEngineSubtypeHeraldField
  extends HeraldField {

  const FIELDCONST = 'subtype';

  public function getHeraldFieldName() {
    return pht('Subtype');
  }

  public function getFieldGroupKey() {
    return HeraldSupportFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorEditEngineSubtypeInterface);
  }

  public function getHeraldFieldValue($object) {
    return $object->getEditEngineSubtype();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    $object = $this->getAdapter()->getObject();
    $map = $object->newEditEngineSubtypeMap();
    return $map->newDatasource();
  }

  protected function getDatasourceValueMap() {
    $object = $this->getAdapter()->getObject();
    $map = $object->newEditEngineSubtypeMap();

    $result = array();
    foreach ($map->getSubtypes() as $subtype) {
      $result[$subtype->getKey()] = $subtype->getName();
    }

    return $result;
  }

  public function isFieldAvailable() {
    $object = $this->getAdapter()->getObject();
    $map = $object->newEditEngineSubtypeMap();
    return ($map->getCount() > 1);
  }

}
