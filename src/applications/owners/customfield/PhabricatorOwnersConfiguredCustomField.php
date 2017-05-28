<?php

final class PhabricatorOwnersConfiguredCustomField
  extends PhabricatorOwnersCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'owners';
  }

  public function createFields($object) {
    $config = PhabricatorEnv::getEnvConfig('owners.custom-field-definitions');

    $fields = PhabricatorStandardCustomField::buildStandardFields(
      $this,
      $config);

    return $fields;
  }

}
