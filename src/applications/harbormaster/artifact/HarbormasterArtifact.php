<?php


abstract class HarbormasterArtifact extends Phobject {

  private $buildArtifact;

  abstract public function getArtifactTypeName();

  public function getArtifactTypeSummary() {
    return $this->getArtifactTypeDescription();
  }

  abstract public function getArtifactTypeDescription();
  abstract public function getArtifactParameterSpecification();
  abstract public function getArtifactParameterDescriptions();
  abstract public function willCreateArtifact(PhabricatorUser $actor);

  public function validateArtifactData(array $artifact_data) {
    $artifact_spec = $this->getArtifactParameterSpecification();
    PhutilTypeSpec::checkMap($artifact_data, $artifact_spec);
  }

  public function renderArtifactSummary(PhabricatorUser $viewer) {
    return null;
  }

  public function releaseArtifact(PhabricatorUser $actor) {
    return;
  }

  public function getArtifactDataExample() {
    return null;
  }

  public function setBuildArtifact(HarbormasterBuildArtifact $build_artifact) {
    $this->buildArtifact = $build_artifact;
    return $this;
  }

  public function getBuildArtifact() {
    return $this->buildArtifact;
  }

  final public function getArtifactConstant() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('ARTIFACTCONST');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'ARTIFACTCONST'));
    }

    $limit = self::getArtifactConstantByteLimit();
    if (!is_string($const) || (strlen($const) > $limit)) {
      throw new Exception(
        pht(
          '"%s" class "%s" has an invalid "%s" property. Action constants '.
          'must be strings and no more than %s bytes in length.',
          __CLASS__,
          get_class($this),
          'ARTIFACTCONST',
          new PhutilNumber($limit)));
    }

    return $const;
  }

  final public static function getArtifactConstantByteLimit() {
    return 32;
  }

  final public static function getAllArtifactTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getArtifactConstant')
      ->execute();
  }

  final public static function getArtifactType($type) {
    return idx(self::getAllArtifactTypes(), $type);
  }

}
