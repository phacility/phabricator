<?php

final class ManiphestPointsConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'maniphest.points';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    ManiphestTaskPoints::validateConfiguration($value);
  }

}
