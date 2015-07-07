<?php

final class PhabricatorMetaMTAApplicationEmailHeraldField
  extends HeraldField {

  const FIELDCONST = 'application-email';

  public function getHeraldFieldName() {
    return pht('Receiving email address');
  }

  public function supportsObject($object) {
    return $this->getAdapter()->supportsApplicationEmail();
  }

  public function getHeraldFieldValue($object) {
    $phids = array();

    $email = $this->getAdapter()->getApplicationEmail();
    if ($email) {
      $phids[] = $email->getPHID();
    }

    return $phids;
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
        return HeraldAdapter::VALUE_APPLICATION_EMAIL;
    }
  }

}
