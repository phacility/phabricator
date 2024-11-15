<?php

final class HeraldExactRolesField extends HeraldField {

  const FIELDCONST = 'roles.exact';

  public function getHeraldFieldName() {
    return pht('Roles being edited');
  }

  public function getFieldGroupKey() {
    return PhabricatorRoleHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorRole);
  }

  public function getHeraldFieldValue($object) {
    return array($object->getPHID());
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorRoleDatasource();
  }

}
