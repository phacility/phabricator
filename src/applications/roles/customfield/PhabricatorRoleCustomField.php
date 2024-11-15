<?php

abstract class PhabricatorRoleCustomField
  extends PhabricatorCustomField {

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
