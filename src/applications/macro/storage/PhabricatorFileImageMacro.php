<?php

final class PhabricatorFileImageMacro extends PhabricatorFileDAO
  implements PhabricatorSubscribableInterface {

  protected $filePHID;
  protected $phid;
  protected $name;
  protected $isDisabled = 0;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID  => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_MCRO);
  }

  public function isAutomaticallySubscribed($phid) {
    return false;
  }

}

