<?php

final class PhabricatorRoleIconsConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'role.icons';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorRoleIconSet::validateConfiguration($value);
  }

}
