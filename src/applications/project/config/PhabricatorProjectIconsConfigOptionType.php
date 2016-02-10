<?php

final class PhabricatorProjectIconsConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    PhabricatorProjectIconSet::validateConfiguration($value);
  }

}
