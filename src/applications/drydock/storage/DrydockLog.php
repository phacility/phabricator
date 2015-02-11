<?php

final class DrydockLog extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $resourceID;
  protected $leaseID;
  protected $epoch;
  protected $message;

  private $resource = self::ATTACHABLE;
  private $lease = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'resourceID' => 'id?',
        'leaseID' => 'id?',
        'message' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'resourceID' => array(
          'columns' => array('resourceID', 'epoch'),
        ),
        'leaseID' => array(
          'columns' => array('leaseID', 'epoch'),
        ),
        'epoch' => array(
          'columns' => array('epoch'),
        ),
      ),
    ) + parent::getConfiguration();
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


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    if ($this->getResource()) {
      return $this->getResource()->getPolicy($capability);
    }
    return $this->getLease()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->getResource()) {
      return $this->getResource()->hasAutomaticCapability($capability, $viewer);
    }
    return $this->getLease()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Logs inherit the policy of their resources.');
  }

}
