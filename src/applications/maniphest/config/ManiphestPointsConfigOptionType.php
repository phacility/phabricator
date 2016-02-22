<?php

final class ManiphestPointsConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    ManiphestTaskPoints::validateConfiguration($value);
  }

}
