<?php

abstract class PhabricatorProjectStandardCustomField
  extends PhabricatorProjectCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'project:internal';
  }

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
