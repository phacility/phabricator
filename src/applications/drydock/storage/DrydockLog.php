<?php

final class DrydockLog extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $resourceID;
  protected $leaseID;
  protected $epoch;
  protected $message;

  private $resource = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function attachResource(DrydockResource $resource) {
    $this->resource = $resource;
    return $this;
  }

  public function getResource() {
    return $this->assertAttached($this->resource);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getResource()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getResource()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Logs inherit the policy of their resources.');
  }

}
