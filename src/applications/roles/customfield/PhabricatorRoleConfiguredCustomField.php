<?php

final class PhabricatorRoleConfiguredCustomField
  extends PhabricatorRoleStandardCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'role';
  }

  public function createFields($object) {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      PhabricatorEnv::getEnvConfig('roles.custom-field-definitions'));
  }

}
