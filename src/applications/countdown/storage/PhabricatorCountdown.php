<?php

final class PhabricatorCountdown extends PhabricatorCountdownDAO {

  protected $id;
  protected $phid;
  protected $title;
  protected $authorPHID;
  protected $epoch;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('CDWN');
  }

}
