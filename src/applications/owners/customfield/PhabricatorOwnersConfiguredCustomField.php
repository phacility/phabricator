<?php

final class PhabricatorOwnersConfiguredCustomField
  extends PhabricatorOwnersCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'owners';
  }

  public function createFields($object) {
    $config = PhabricatorEnv::getEnvConfig(
      'owners.custom-field-definitions',
      array());

    $fields = PhabricatorStandardCustomField::buildStandardFields(
      $this,
      $config);

    return $fields;
  }

}
