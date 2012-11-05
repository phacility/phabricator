<?php

final class PhabricatorMetaMTAMailingList extends PhabricatorMetaMTADAO {

  protected $name;
  protected $phid;
  protected $email;
  protected $uri;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_MLST);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

}
