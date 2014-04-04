<?php

final class ManiphestConfiguredCustomField
  extends ManiphestCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'maniphest';
  }

  public function createFields($object) {
    $config = PhabricatorEnv::getEnvConfig(
      'maniphest.custom-field-definitions',
      array());
    $fields = PhabricatorStandardCustomField::buildStandardFields(
      $this,
      $config);

    return $fields;
  }

}
