<?php

final class PhabricatorProjectConfiguredCustomField
  extends PhabricatorProjectCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'project';
  }

  public function createFields() {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      PhabricatorEnv::getEnvConfig(
        'projects.custom-field-definitions',
        array()));
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
