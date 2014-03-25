<?php

final class PhabricatorProjectConfiguredCustomField
  extends PhabricatorProjectStandardCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'project';
  }

  public function createFields($object) {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      PhabricatorEnv::getEnvConfig(
        'projects.custom-field-definitions',
        array()));
  }

}
