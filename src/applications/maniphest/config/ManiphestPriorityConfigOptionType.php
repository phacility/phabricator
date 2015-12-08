<?php

final class ManiphestPriorityConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    ManiphestTaskPriority::validateConfiguration($value);
  }

}
