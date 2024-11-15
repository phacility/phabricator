<?php

final class PhabricatorRoleMembersPolicyRule extends PhabricatorPolicyRule {

  private $memberships = array();

  public function getRuleDescription() {
    return pht('members of role');
  }

  public function willApplyRules(
    PhabricatorUser $viewer,
    array $values,
    array $objects) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return;
    }

    if (empty($this->memberships[$viewer_phid])) {
      $this->memberships[$viewer_phid] = array();
    }

    foreach ($objects as $key => $object) {
      $cache = $this->getTransactionHint($object);
      if ($cache === null) {
        continue;
      }

      unset($objects[$key]);

      if (isset($cache[$viewer_phid])) {
        $this->memberships[$viewer_phid][$object->getPHID()] = true;
      }
    }

    if (!$objects) {
      return;
    }

    $object_phids = mpull($objects, 'getPHID');
    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($viewer_phid))
      ->withDestinationPHIDs($object_phids)
      ->withEdgeTypes(
        array(
          PhabricatorRoleMemberOfRoleEdgeType::EDGECONST,
        ));
    $edge_query->execute();

    $memberships = $edge_query->getDestinationPHIDs();
    if (!$memberships) {
      return;
    }

    $this->memberships[$viewer_phid] += array_fill_keys($memberships, true);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {
    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    $memberships = idx($this->memberships, $viewer_phid);
    return isset($memberships[$object->getPHID()]);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof PhabricatorRole);
  }

  public function getObjectPolicyKey() {
    return 'role.members';
  }

  public function getObjectPolicyName() {
    return pht('Role Members');
  }

  public function getObjectPolicyIcon() {
    return 'fa-users';
  }

  public function getPolicyExplanation() {
    return pht('Role members can take this action.');
  }

}
