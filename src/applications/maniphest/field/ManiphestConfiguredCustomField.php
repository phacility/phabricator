<?php

final class ManiphestConfiguredCustomField
  extends ManiphestCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'maniphest';
  }

  public function createFields($object) {
    $config = PhabricatorEnv::getEnvConfig(
      'maniphest.custom-field-definitions');
    $fields = PhabricatorStandardCustomField::buildStandardFields(
      $this,
      $config);

    return $fields;
  }

}
