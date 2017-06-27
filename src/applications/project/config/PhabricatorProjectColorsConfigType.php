<?php

final class PhabricatorProjectColorsConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'project.colors';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorProjectIconSet::validateColorConfiguration($value);
  }

}
