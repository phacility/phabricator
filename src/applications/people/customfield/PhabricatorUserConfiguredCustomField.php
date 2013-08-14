<?php

final class PhabricatorUserConfiguredCustomField
  extends PhabricatorUserCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'user';
  }

  public function createFields() {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      PhabricatorEnv::getEnvConfig('user.custom-field-definitions', array()));
  }

  public function newStorageObject() {
    return new PhabricatorUserConfiguredCustomFieldStorage();
  }

}
