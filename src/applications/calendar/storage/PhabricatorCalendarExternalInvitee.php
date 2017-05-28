<?php

final class PhabricatorCalendarExternalInvitee
  extends PhabricatorCalendarDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $nameIndex;
  protected $uri;
  protected $parameters = array();
  protected $sourcePHID;

  public static function initializeNewCalendarEventInvitee(
    PhabricatorUser $actor, $event) {
    return id(new PhabricatorCalendarEventInvitee())
      ->setInviterPHID($actor->getPHID())
      ->setStatus(PhabricatorCalendarEventInvitee::STATUS_INVITED)
      ->setEventPHID($event->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'nameIndex' => 'bytes12',
        'uri' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
          'columns' => array('nameIndex'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorCalendarExternalInviteePHIDType::TYPECONST;
  }

  public function save() {
    $this->nameIndex = PhabricatorHash::digestForIndex($this->getName());
    return parent::save();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
