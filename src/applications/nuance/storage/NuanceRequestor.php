<?php

final class NuanceRequestor
  extends NuanceDAO {

  protected $data;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      NuanceRequestorPHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getURI() {
    return '/nuance/requestor/view/'.$this->getID().'/';
  }

  public function getPhabricatorUserPHID() {
    return idx($this->getData(), 'phabricatorUserPHID');
  }

}
