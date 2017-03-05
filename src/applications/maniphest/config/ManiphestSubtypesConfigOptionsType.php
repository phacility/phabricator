<?php

final class ManiphestSubtypesConfigOptionsType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    PhabricatorEditEngineSubtype::validateConfiguration($value);
  }

}
