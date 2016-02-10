<?php

final class DrydockCommand
  extends DrydockDAO
  implements PhabricatorPolicyInterface {

  const COMMAND_RELEASE = 'release';
  const COMMAND_RECLAIM = 'reclaim';

  protected $authorPHID;
  protected $targetPHID;
  protected $command;
  protected $isConsumed;

  private $commandTarget = self::ATTACHABLE;

  public static function initializeNewCommand(PhabricatorUser $author) {
    return id(new DrydockCommand())
      ->setAuthorPHID($author->getPHID())
      ->setIsConsumed(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'command' => 'text32',
        'isConsumed' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_target' => array(
          'columns' => array('targetPHID', 'isConsumed'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function attachCommandTarget($target) {
    $this->commandTarget = $target;
    return $this;
  }

  public function getCommandTarget() {
    return $this->assertAttached($this->commandTarget);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getCommandTarget()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getCommandTarget()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Drydock commands have the same policies as their targets.');
  }

}
