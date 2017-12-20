<?php

final class PhabricatorProjectIconsConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'project.icons';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorProjectIconSet::validateConfiguration($value);
  }

}
