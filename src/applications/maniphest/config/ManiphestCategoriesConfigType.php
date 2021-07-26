<?php

final class ManiphestCategoriesConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'maniphest.categories';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    ManiphestTaskCategory::validateConfiguration($value);
  }

}
