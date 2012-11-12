<?php

final class HarbormasterObject extends HarbormasterDAO {

  protected $phid;
  protected $name;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_TOBJ);
  }

}
