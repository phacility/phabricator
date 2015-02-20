<?php

/**
 * A message sent to an executing build target by an external system. We
 * capture these messages and process them asynchronously to avoid race
 * conditions where we receive a message before a build plan is ready to
 * accept it.
 */
final class HarbormasterBuildMessage extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $authorPHID;
  protected $buildTargetPHID;
  protected $type;
  protected $isConsumed;

  private $buildTarget = self::ATTACHABLE;

  public static function initializeNewMessage(PhabricatorUser $actor) {
    return id(new HarbormasterBuildMessage())
      ->setAuthorPHID($actor->getPHID())
      ->setIsConsumed(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'type' => 'text16',
        'isConsumed' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_buildtarget' => array(
          'columns' => array('buildTargetPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getBuildTarget() {
    return $this->assertAttached($this->buildTarget);
  }

  public function attachBuildTarget(HarbormasterBuildTarget $target) {
    $this->buildTarget = $target;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildTarget()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildTarget()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Build messages have the same policies as their targets.');
  }

}
