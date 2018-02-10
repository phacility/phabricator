<?php

final class HeraldActingUserField
  extends HeraldField {

  const FIELDCONST = 'herald.acting-user';

  public function getHeraldFieldName() {
    return pht('Acting user');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getActingAsPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

  public function supportsObject($object) {
    return true;
  }

  public function getFieldGroupKey() {
    return HeraldEditFieldGroup::FIELDGROUPKEY;
  }

}
