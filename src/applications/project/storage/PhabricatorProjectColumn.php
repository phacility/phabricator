<?php

final class PhabricatorProjectColumn
  extends PhabricatorProjectDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  const STATUS_ACTIVE = 0;
  const STATUS_HIDDEN = 1;

  const DEFAULT_ORDER = 'natural';
  const ORDER_NATURAL = 'natural';
  const ORDER_PRIORITY = 'priority';

  protected $name;
  protected $status;
  protected $projectPHID;
  protected $sequence;
  protected $properties = array();

  private $project = self::ATTACHABLE;

  public static function initializeNewColumn(PhabricatorUser $user) {
    return id(new PhabricatorProjectColumn())
      ->setName('')
      ->setStatus(self::STATUS_ACTIVE);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorProjectColumnPHIDType::TYPECONST);
  }

  public function attachProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->assertAttached($this->project);
  }

  public function isDefaultColumn() {
    return (bool)$this->getProperty('isDefault');
  }

  public function isHidden() {
    return ($this->getStatus() == self::STATUS_HIDDEN);
  }

  public function getDisplayName() {
    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    if ($this->isDefaultColumn()) {
      return pht('Backlog');
    }

    return pht('Unnamed Column');
  }

  public function getHeaderIcon() {
    $icon = null;

    if ($this->isHidden()) {
      $icon = 'fa-eye-slash';
      $text = pht('Hidden');
    }

    if ($this->isDefaultColumn()) {
      $icon = 'fa-archive';
      $text = pht('Default');
    }

    if ($icon) {
      return id(new PHUIIconView())
        ->setIconFont($icon)
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $text,
          ));;
    }

    return null;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getPointLimit() {
    return $this->getProperty('pointLimit');
  }

  public function setPointLimit($limit) {
    $this->setProperty('pointLimit', $limit);
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getProject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getProject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Users must be able to see a project to see its board.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

}
