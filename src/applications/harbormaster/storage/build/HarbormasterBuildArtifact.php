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
  const TYPE_URI = 'uri';

  public static function initializeNewBuildArtifact(
    HarbormasterBuildTarget $build_target) {
    return id(new HarbormasterBuildArtifact())
      ->setBuildTargetPHID($build_target->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'artifactData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'artifactType' => 'text32',
        'artifactIndex' => 'bytes12',
        'artifactKey' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_artifact' => array(
          'columns' => array('artifactType', 'artifactIndex'),
          'unique' => true,
        ),
        'key_garbagecollect' => array(
          'columns' => array('artifactType', 'dateCreated'),
        ),
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

  public function setArtifactKey($build_phid, $build_gen, $key) {
    $this->artifactIndex =
      PhabricatorHash::digestForIndex($build_phid.$build_gen.$key);
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
          ->setViewer($viewer)
          ->withIDs(array($data['drydock-lease']))
          ->execute();
        $lease = idx($leases, $data['drydock-lease']);

        return id(new PHUIObjectItemView())
          ->setObjectName(pht('Drydock Lease'))
          ->setHeader($lease->getID())
          ->setHref('/drydock/lease/'.$lease->getID());
      case self::TYPE_URI:
        return id(new PHUIObjectItemView())
          ->setObjectName($data['name'])
          ->setHeader($data['uri'])
          ->setHref($data['uri']);
      default:
        return null;
    }
  }

  public function loadDrydockLease() {
    if ($this->getArtifactType() !== self::TYPE_HOST) {
      throw new Exception(
        pht(
          '`%s` may only be called on host artifacts.',
          __FUNCTION__));
    }

    $data = $this->getArtifactData();

    // FIXME: Is there a better way of doing this?
    // TODO: Policy stuff, etc.
    $lease = id(new DrydockLease())->load(
      $data['drydock-lease']);
    if ($lease === null) {
      throw new Exception(pht('Associated Drydock lease not found!'));
    }
    $resource = id(new DrydockResource())->load(
      $lease->getResourceID());
    if ($resource === null) {
      throw new Exception(pht('Associated Drydock resource not found!'));
    }
    $lease->attachResource($resource);

    return $lease;
  }

  public function loadPhabricatorFile() {
    if ($this->getArtifactType() !== self::TYPE_FILE) {
      throw new Exception(
        pht(
          '`%s` may only be called on file artifacts.',
          __FUNCTION__));
    }

    $data = $this->getArtifactData();

    // The data for TYPE_FILE is an array with a single PHID in it.
    $phid = $data['filePHID'];

    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    if ($file === null) {
      throw new Exception(pht('Associated file not found!'));
    }
    return $file;
  }

  public function release() {
    switch ($this->getArtifactType()) {
      case self::TYPE_HOST:
        $this->releaseDrydockLease();
        break;
    }
  }

  public function releaseDrydockLease() {
    $lease = $this->loadDrydockLease();
    $resource = $lease->getResource();
    $blueprint = $resource->getBlueprint();

    if ($lease->isActive()) {
      $blueprint->releaseLease($resource, $lease);
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
    return pht('Users must be able to see a buildable to see its artifacts.');
  }

}
