<?php

final class PhabricatorProjectColumnPosition extends PhabricatorProjectDAO
  implements PhabricatorPolicyInterface {

  protected $boardPHID;
  protected $columnPHID;
  protected $objectPHID;
  protected $sequence;

  private $column = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function getColumn() {
    return $this->assertAttached($this->column);
  }

  public function attachColumn(PhabricatorProjectColumn $column) {
    $this->column = $column;
    return $this;
  }

  public function getOrderingKey() {
    // Low sequence numbers go above high sequence numbers.
    // High position IDs go above low position IDs.
    // Broadly, this makes newly added stuff float to the top.

    return sprintf(
      '~%012d%012d',
      $this->getSequence(),
      ((1 << 31) - $this->getID()));
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
