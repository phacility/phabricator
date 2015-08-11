<?php

final class HeraldSubscribersField extends HeraldField {

  const FIELDCONST = 'cc';

  public function getHeraldFieldName() {
    return pht('Subscribers');
  }

  public function getFieldGroupKey() {
    return HeraldSupportFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function getHeraldFieldValue($object) {
    $phid = $object->getPHID();
    return PhabricatorSubscribersQuery::loadSubscribersForPHID($phid);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectOrUserDatasource();
  }

}
