<?php

final class PhabricatorRoleSubtypesConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'roles.subtypes';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorEditEngineSubtype::validateConfiguration($value);
  }

}
