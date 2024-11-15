<?php

final class PhabricatorRoleColorsConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'role.colors';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorRoleIconSet::validateColorConfiguration($value);
  }

}
