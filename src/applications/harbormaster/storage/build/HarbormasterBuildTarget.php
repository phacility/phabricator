<?php

final class HarbormasterBuildTarget extends HarbormasterDAO {

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuildTarget::TYPECONST);
  }

}
