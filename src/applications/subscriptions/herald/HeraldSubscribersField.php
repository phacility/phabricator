<?php

final class HeraldSubscribersField extends HeraldField {

  const FIELDCONST = 'cc';

  public function getHeraldFieldName() {
    return pht('Subscribers');
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function getHeraldFieldValue($object) {
    $phid = $object->getPHID();
    return PhabricatorSubscribersQuery::loadSubscribersForPHID($phid);
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_USER_OR_PROJECT;
    }
  }

}
