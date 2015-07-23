<?php

final class PhabricatorCalendarEventInvitee extends PhabricatorCalendarDAO
  implements PhabricatorPolicyInterface {

  protected $eventPHID;
  protected $inviteePHID;
  protected $inviterPHID;
  protected $status;

  const STATUS_INVITED = 'invited';
  const STATUS_ATTENDING = 'attending';
  const STATUS_DECLINED = 'declined';
  const STATUS_UNINVITED = 'uninvited';

  public static function initializeNewCalendarEventInvitee(
    PhabricatorUser $actor, $event) {
    return id(new PhabricatorCalendarEventInvitee())
      ->setInviterPHID($actor->getPHID())
      ->setStatus(self::STATUS_INVITED)
      ->setEventPHID($event->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_event' => array(
          'columns' => array('eventPHID', 'inviteePHID'),
          'unique' => true,
        ),
        'key_invitee' => array(
          'columns' => array('inviteePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function isAttending() {
    return ($this->getStatus() == self::STATUS_ATTENDING);
  }

  public function isUninvited() {
    if ($this->getStatus() == self::STATUS_UNINVITED) {
      return true;
    } else {
      return false;
    }
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

  public function describeAutomaticCapability($capability) {
    return null;
  }
}
