<?php

final class HarbormasterBuildArtifact extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildTargetPHID;
  protected $artifactType;
  protected $artifactIndex;
  protected $artifactKey;
  protected $artifactData = array();

  private $buildTarget = self::ATTACHABLE;

  const TYPE_FILE = 'file';
  const TYPE_HOST = 'host';

  public static function initializeNewBuildArtifact(
    HarbormasterBuildTarget $build_target) {
    return id(new HarbormasterBuildArtifact())
      ->setBuildTargetPHID($build_target->getPHID());
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'artifactData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function attachBuildTarget(HarbormasterBuildTarget $build_target) {
    $this->buildTarget = $build_target;
    return $this;
  }

  public function getBuildTarget() {
    return $this->assertAttached($this->buildTarget);
  }

  public function setArtifactKey($build_phid, $key) {
    $this->artifactIndex =
      PhabricatorHash::digestForIndex($build_phid.$key);
    $this->artifactKey = $key;
    return $this;
  }

  public function getObjectItemView(PhabricatorUser $viewer) {
    $data = $this->getArtifactData();
    switch ($this->getArtifactType()) {
      case self::TYPE_FILE:
        $handle = id(new PhabricatorHandleQuery())
          ->setViewer($viewer)
          ->withPHIDs($data)
          ->executeOne();

        return id(new PHUIObjectItemView())
          ->setObjectName(pht('File'))
          ->setHeader($handle->getFullName())
          ->setHref($handle->getURI());
      case self::TYPE_HOST:
        $leases = id(new DrydockLeaseQuery())
          ->withIDs(array($data["drydock-lease"]))
          ->execute();
        $lease = $leases[$data["drydock-lease"]];

        return id(new PHUIObjectItemView())
          ->setObjectName(pht('Drydock Lease'))
          ->setHeader($lease->getID())
          ->setHref('/drydock/lease/'.$lease->getID());
      default:
        return null;
    }
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
    return pht(
      'Users must be able to see a buildable to see its artifacts.');
  }

}
