<?php

final class PhabricatorProjectTypeConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    PhabricatorProjectIconSet::validateConfiguration($value);
  }

}
