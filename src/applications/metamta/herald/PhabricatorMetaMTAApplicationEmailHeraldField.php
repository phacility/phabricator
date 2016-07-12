<?php

final class PhabricatorMetaMTAApplicationEmailHeraldField
  extends HeraldField {

  const FIELDCONST = 'application-email';

  public function getHeraldFieldName() {
    return pht('Receiving email addresses');
  }

  public function getFieldGroupKey() {
    return HeraldEditFieldGroup::FIELDGROUPKEY;
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

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorMetaMTAApplicationEmailDatasource();
  }

}
