<?php

abstract class ManiphestCustomField
  extends PhabricatorCustomField {

  public function newStorageObject() {
    return new ManiphestCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new ManiphestCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new ManiphestCustomFieldNumericIndex();
  }

}
