<?php

final class ManiphestStatusConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    ManiphestTaskStatus::validateConfiguration($value);
  }

}
