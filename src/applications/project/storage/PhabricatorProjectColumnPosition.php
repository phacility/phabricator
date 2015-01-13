<?php

final class PhabricatorProjectColumnPosition extends PhabricatorProjectDAO
  implements PhabricatorPolicyInterface {

  protected $boardPHID;
  protected $columnPHID;
  protected $objectPHID;
  protected $sequence;

  private $column = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'sequence' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'boardPHID' => array(
          'columns' => array('boardPHID', 'columnPHID', 'objectPHID'),
          'unique' => true,
        ),
        'objectPHID' => array(
          'columns' => array('objectPHID', 'boardPHID'),
        ),
        'boardPHID_2' => array(
          'columns' => array('boardPHID', 'columnPHID', 'sequence'),
        ),
      ),
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
