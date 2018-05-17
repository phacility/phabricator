<?php

/**
 * A message sent to an executing build target by an external system. We
 * capture these messages and process them asynchronously to avoid race
 * conditions where we receive a message before a build plan is ready to
 * accept it.
 */
final class HarbormasterBuildMessage
  extends HarbormasterDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $authorPHID;
  protected $receiverPHID;
  protected $type;
  protected $isConsumed;

  private $receiver = self::ATTACHABLE;

  public static function initializeNewMessage(PhabricatorUser $actor) {
    $actor_phid = $actor->getPHID();
    if (!$actor_phid) {
      $actor_phid = id(new PhabricatorHarbormasterApplication())->getPHID();
    }

    return id(new HarbormasterBuildMessage())
      ->setAuthorPHID($actor_phid)
      ->setIsConsumed(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'type' => 'text16',
        'isConsumed' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_receiver' => array(
          'columns' => array('receiverPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getReceiver() {
    return $this->assertAttached($this->receiver);
  }

  public function attachReceiver($receiver) {
    $this->receiver = $receiver;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getReceiver()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getReceiver()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Build messages have the same policies as their receivers.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }

}
