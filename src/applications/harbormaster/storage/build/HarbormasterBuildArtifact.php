<?php

final class HarbormasterBuildArtifact
  extends HarbormasterDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface {

  protected $buildTargetPHID;
  protected $artifactType;
  protected $artifactIndex;
  protected $artifactKey;
  protected $artifactData = array();
  protected $isReleased = 0;

  private $buildTarget = self::ATTACHABLE;
  private $artifactImplementation;

  public static function initializeNewBuildArtifact(
    HarbormasterBuildTarget $build_target) {

    return id(new HarbormasterBuildArtifact())
      ->attachBuildTarget($build_target)
      ->setBuildTargetPHID($build_target->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'artifactData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'artifactType' => 'text32',
        'artifactIndex' => 'bytes12',
        'artifactKey' => 'text255',
        'isReleased' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_artifact' => array(
          'columns' => array('artifactType', 'artifactIndex'),
          'unique' => true,
        ),
        'key_garbagecollect' => array(
          'columns' => array('artifactType', 'dateCreated'),
        ),
        'key_target' => array(
          'columns' => array('buildTargetPHID', 'artifactType'),
        ),
        'key_index' => array(
          'columns' => array('artifactIndex'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return HarbormasterBuildArtifactPHIDType::TYPECONST;
  }

  public function attachBuildTarget(HarbormasterBuildTarget $build_target) {
    $this->buildTarget = $build_target;
    return $this;
  }

  public function getBuildTarget() {
    return $this->assertAttached($this->buildTarget);
  }

  public function setArtifactKey($key) {
    $target = $this->getBuildTarget();
    $this->artifactIndex = self::getArtifactIndex($target, $key);
    $this->artifactKey = $key;
    return $this;
  }

  public static function getArtifactIndex(
    HarbormasterBuildTarget $target,
    $artifact_key) {

    $build = $target->getBuild();

    $parts = array(
      $build->getPHID(),
      $target->getBuildGeneration(),
      $artifact_key,
    );
    $parts = implode("\0", $parts);

    return PhabricatorHash::digestForIndex($parts);
  }

  public function releaseArtifact() {
    if ($this->getIsReleased()) {
      return $this;
    }

    $impl = $this->getArtifactImplementation();
    if ($impl) {
      $impl->releaseArtifact(PhabricatorUser::getOmnipotentUser());
    }

    return $this
      ->setIsReleased(1)
      ->save();
  }

  public function getArtifactImplementation() {
    if ($this->artifactImplementation === null) {
      $type = $this->getArtifactType();
      $impl = HarbormasterArtifact::getArtifactType($type);
      if (!$impl) {
        return null;
      }

      $impl = clone $impl;
      $impl->setBuildArtifact($this);
      $this->artifactImplementation = $impl;
    }

    return $this->artifactImplementation;
  }


  public function getProperty($key, $default = null) {
    return idx($this->artifactData, $key, $default);
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
      'Users must be able to see a build target to see its artifacts.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $viewer = $this->getViewer();

    $this->openTransaction();
      $this->releaseArtifact($viewer);
      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */

  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('buildTargetPHID')
        ->setType('phid')
        ->setDescription(pht('The build target this artifact is attached to.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('artifactType')
        ->setType('string')
        ->setDescription(pht('The artifact type.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('artifactKey')
        ->setType('string')
        ->setDescription(pht('The artifact key.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isReleased')
        ->setType('bool')
        ->setDescription(pht('True if this artifact has been released.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'buildTargetPHID' => $this->getBuildTargetPHID(),
      'artifactType' => $this->getArtifactType(),
      'artifactKey' => $this->getArtifactKey(),
      'artifactData' => $this->artifactData,
      'isReleased' => (bool)$this->getIsReleased(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }
}
