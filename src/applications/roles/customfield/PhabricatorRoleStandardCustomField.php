<?php

abstract class PhabricatorRoleStandardCustomField
  extends PhabricatorRoleCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'role:internal';
  }

  public function newStorageObject() {
    return new PhabricatorRoleCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new PhabricatorRoleCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new PhabricatorRoleCustomFieldNumericIndex();
  }

}
