<?php

final class ManiphestSubtypesConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'maniphest.subtypes';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    PhabricatorEditEngineSubtype::validateConfiguration($value);
  }

}
