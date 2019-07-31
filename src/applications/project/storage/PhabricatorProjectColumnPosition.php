<?php

final class PhabricatorProjectColumnPosition extends PhabricatorProjectDAO
  implements PhabricatorPolicyInterface {

  protected $boardPHID;
  protected $columnPHID;
  protected $objectPHID;
  protected $sequence;

  private $column = self::ATTACHABLE;
  private $viewSequence = 0;

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

  public function setViewSequence($view_sequence) {
    $this->viewSequence = $view_sequence;
    return $this;
  }

  public function newColumnPositionOrderVector() {
    // We're ordering both real positions and "virtual" positions which we have
    // created but not saved yet.

    // Low sequence numbers go above high sequence numbers. Virtual positions
    // will have sequence number 0.

    // High virtual sequence numbers go above low virtual sequence numbers.
    // The layout engine gets objects in ID order, and this puts them in
    // reverse ID order.

    // High IDs go above low IDs.

    // Broadly, this collectively makes newly added stuff float to the top.

    return id(new PhutilSortVector())
      ->addInt($this->getSequence())
      ->addInt(-1 * $this->viewSequence)
      ->addInt(-1 * $this->getID());
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
