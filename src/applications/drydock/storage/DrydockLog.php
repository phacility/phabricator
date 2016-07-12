<?php

final class DrydockLog extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $blueprintPHID;
  protected $resourcePHID;
  protected $leasePHID;
  protected $epoch;
  protected $type;
  protected $data = array();

  private $blueprint = self::ATTACHABLE;
  private $resource = self::ATTACHABLE;
  private $lease = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'blueprintPHID' => 'phid?',
        'resourcePHID' => 'phid?',
        'leasePHID' => 'phid?',
        'type' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_blueprint' => array(
          'columns' => array('blueprintPHID', 'type'),
        ),
        'key_resource' => array(
          'columns' => array('resourcePHID', 'type'),
        ),
        'key_lease' => array(
          'columns' => array('leasePHID', 'type'),
        ),
        'epoch' => array(
          'columns' => array('epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function attachBlueprint(DrydockBlueprint $blueprint = null) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->assertAttached($this->blueprint);
  }

  public function attachResource(DrydockResource $resource = null) {
    $this->resource = $resource;
    return $this;
  }

  public function getResource() {
    return $this->assertAttached($this->resource);
  }

  public function attachLease(DrydockLease $lease = null) {
    $this->lease = $lease;
    return $this;
  }

  public function getLease() {
    return $this->assertAttached($this->lease);
  }

  public function isComplete() {
    if ($this->getBlueprintPHID() && !$this->getBlueprint()) {
      return false;
    }

    if ($this->getResourcePHID() && !$this->getResource()) {
      return false;
    }

    if ($this->getLeasePHID() && !$this->getLease()) {
      return false;
    }

    return true;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // NOTE: We let you see that logs exist no matter what, but don't actually
    // show you log content unless you can see all of the associated objects.
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'To view log details, you must be able to view the associated '.
      'blueprint, resource and lease.');
  }

}
