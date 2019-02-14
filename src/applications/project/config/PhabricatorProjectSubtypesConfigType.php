<?php

final class PhabricatorProjectSubtypesConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'projects.subtypes';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorEditEngineSubtype::validateConfiguration($value);
  }

}
