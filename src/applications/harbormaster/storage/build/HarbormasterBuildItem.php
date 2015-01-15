<?php

final class HarbormasterBuildItem extends HarbormasterDAO {

  protected $name;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_NO_TABLE => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildItemPHIDType::TYPECONST);
  }

}
