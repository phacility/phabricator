<?php

final class HeraldSpaceField extends HeraldField {

  const FIELDCONST = 'space';

  public function getHeraldFieldName() {
    return pht('Space');
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSpacesInterface);
  }

  public function getHeraldFieldValue($object) {
    return PhabricatorSpacesNamespaceQuery::getObjectSpacePHID($object);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_SPACE;
  }

}
