<?php

abstract class PhabricatorObjectRelationship extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public function getRelationshipConstant() {
    return $this->getPhobjectClassConstant('RELATIONSHIPKEY');
  }

  abstract public function isEnabledForObject($object);

  abstract public function getEdgeConstant();

  abstract protected function getActionName();
  abstract protected function getActionIcon();

  public function shouldAppearInActionMenu() {
    return true;
  }

  protected function isActionEnabled($object) {
    $viewer = $this->getViewer();

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  final public function newAction($object) {
    $is_enabled = $this->isActionEnabled($object);
    $action_uri = $this->getActionURI($object);

    return id(new PhabricatorActionView())
      ->setName($this->getActionName())
      ->setHref($action_uri)
      ->setIcon($this->getActionIcon())
      ->setDisabled(!$is_enabled)
      ->setWorkflow(true);
  }

  final public static function getAllRelationships() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getRelationshipConstant')
      ->execute();
  }

  private function getActionURI($object) {
    $phid = $object->getPHID();

    // TODO: Remove this, this is just legacy support for the current
    // controller until a new one gets built.
    $legacy_kinds = array(
      ManiphestTaskHasCommitEdgeType::EDGECONST => 'CMIT',
      ManiphestTaskHasMockEdgeType::EDGECONST => 'MOCK',
      ManiphestTaskHasRevisionEdgeType::EDGECONST => 'DREV',
    );

    $edge_type = $this->getEdgeConstant();
    $legacy_kind = idx($legacy_kinds, $edge_type);
    if (!$legacy_kind) {
      throw new Exception(
        pht(
          'Only specific legacy relationships are supported!'));
    }

    return "/search/attach/{$phid}/{$legacy_kind}/";
  }

}
