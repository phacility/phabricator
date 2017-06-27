<?php

final class ManiphestStatusesConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'maniphest.statuses';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    ManiphestTaskStatus::validateConfiguration($value);
  }

}
