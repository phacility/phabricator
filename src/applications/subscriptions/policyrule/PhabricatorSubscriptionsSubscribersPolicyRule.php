<?php

final class PhabricatorSubscriptionsSubscribersPolicyRule
  extends PhabricatorPolicyRule {

  private $subscribed = array();
  private $sourcePHIDs = array();

  public function getObjectPolicyKey() {
    return 'subscriptions.subscribers';
  }

  public function getObjectPolicyName() {
    return pht('Subscribers');
  }

  public function getPolicyExplanation() {
    return pht('Subscribers can take this action.');
  }

  public function getRuleDescription() {
    return pht('subscribers');
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function willApplyRules(
    PhabricatorUser $viewer,
    array $values,
    array $objects) {

    // We want to let the user see the object if they're a subscriber or
    // a member of any project which is a subscriber. Additionally, because
    // subscriber state is complex, we need to read hints passed from
    // the TransactionEditor to predict policy state after transactions apply.

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return;
    }

    if (empty($this->subscribed[$viewer_phid])) {
      $this->subscribed[$viewer_phid] = array();
    }

    // Load the project PHIDs the user is a member of.
    if (!isset($this->sourcePHIDs[$viewer_phid])) {
      $source_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $viewer_phid,
        PhabricatorProjectMemberOfProjectEdgeType::EDGECONST);
      $source_phids[] = $viewer_phid;
      $this->sourcePHIDs[$viewer_phid] = $source_phids;
    }

    // Look for transaction hints.
    foreach ($objects as $key => $object) {
      $cache = $this->getTransactionHint($object);
      if ($cache === null) {
        // We don't have a hint for this object, so we'll deal with it below.
        continue;
      }

      // We have a hint, so use that as the source of truth.
      unset($objects[$key]);

      foreach ($this->sourcePHIDs[$viewer_phid] as $source_phid) {
        if (isset($cache[$source_phid])) {
          $this->subscribed[$viewer_phid][$object->getPHID()] = true;
          break;
        }
      }
    }

    $phids = mpull($objects, 'getPHID');
    if (!$phids) {
      return;
    }

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($this->sourcePHIDs[$viewer_phid])
      ->withEdgeTypes(
        array(
          PhabricatorSubscribedToObjectEdgeType::EDGECONST,
        ))
      ->withDestinationPHIDs($phids);

    $edge_query->execute();

    $subscribed = $edge_query->getDestinationPHIDs();
    if (!$subscribed) {
      return;
    }

    $this->subscribed[$viewer_phid] += array_fill_keys($subscribed, true);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    if ($object->isAutomaticallySubscribed($viewer_phid)) {
      return true;
    }

    $subscribed = idx($this->subscribed, $viewer_phid);
    return isset($subscribed[$object->getPHID()]);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
