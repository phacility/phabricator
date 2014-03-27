<?php

final class PhabricatorUserConfiguredCustomField
  extends PhabricatorUserCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'user';
  }

  public function createFields($object) {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      PhabricatorEnv::getEnvConfig('user.custom-field-definitions', array()));
  }

  public function newStorageObject() {
    return new PhabricatorUserConfiguredCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new PhabricatorUserCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new PhabricatorUserCustomFieldNumericIndex();
  }

}
