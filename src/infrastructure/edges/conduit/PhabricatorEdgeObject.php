<?php

final class PhabricatorEdgeObject
  extends Phobject
  implements PhabricatorPolicyInterface {

  private $id;
  private $src;
  private $dst;
  private $type;

  public static function newFromRow(array $row) {
    $edge = new self();

    $edge->id = $row['id'];
    $edge->src = $row['src'];
    $edge->dst = $row['dst'];
    $edge->type = $row['type'];

    return $edge;
  }

  public function getID() {
    return $this->id;
  }

  public function getSourcePHID() {
    return $this->src;
  }

  public function getEdgeType() {
    return $this->type;
  }

  public function getDestinationPHID() {
    return $this->dst;
  }

  public function getPHID() {
    return null;
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
