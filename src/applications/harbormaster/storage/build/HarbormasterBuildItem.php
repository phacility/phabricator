<?php

final class HarbormasterBuildItem extends HarbormasterDAO {

  protected $name;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuildItem::TYPECONST);
  }

}
