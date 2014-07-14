<?php

abstract class PhabricatorProjectCustomField
  extends PhabricatorCustomField {

  public function newStorageObject() {
    return new PhabricatorProjectCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new PhabricatorProjectCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new PhabricatorProjectCustomFieldNumericIndex();
  }

}
