<?php

final class ManiphestPrioritiesConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'maniphest.priorities';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    ManiphestTaskPriority::validateConfiguration($value);
  }

}
