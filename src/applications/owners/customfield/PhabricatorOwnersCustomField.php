<?php

abstract class PhabricatorOwnersCustomField
  extends PhabricatorCustomField {

  public function newStorageObject() {
    return new PhabricatorOwnersCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new PhabricatorOwnersCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new PhabricatorOwnersCustomFieldNumericIndex();
  }

}
