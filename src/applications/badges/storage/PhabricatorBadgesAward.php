<?php

final class PhabricatorBadgesAward extends PhabricatorBadgesDAO
  implements
    PhabricatorDestructibleInterface,
    PhabricatorPolicyInterface {

  protected $badgePHID;
  protected $recipientPHID;
  protected $awarderPHID;

  private $badge = self::ATTACHABLE;

  public static function initializeNewBadgesAward(
    PhabricatorUser $actor,
    PhabricatorBadgesBadge $badge,
    $recipient_phid) {
    return id(new self())
      ->setRecipientPHID($recipient_phid)
      ->setBadgePHID($badge->getPHID())
      ->setAwarderPHID($actor->getPHID())
      ->attachBadge($badge);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_KEY_SCHEMA => array(
        'key_badge' => array(
          'columns' => array('badgePHID', 'recipientPHID'),
          'unique' => true,
        ),
        'key_recipient' => array(
          'columns' => array('recipientPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function attachBadge(PhabricatorBadgesBadge $badge) {
    $this->badge = $badge;
    return $this;
  }

  public function getBadge() {
    return $this->assertAttached($this->badge);
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getBadge()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
