<?php

final class PhabricatorProjectColorsConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    PhabricatorProjectIconSet::validateColorConfiguration($value);
  }

}
