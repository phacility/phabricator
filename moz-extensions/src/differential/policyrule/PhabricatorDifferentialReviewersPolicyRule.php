<?php

final class PhabricatorDifferentialReviewersPolicyRule
  extends PhabricatorPolicyRule {

  private $reviewing = array();
  private $sourcePHIDs = array();

  public function getObjectPolicyKey() {
    return 'differential.reviewers';
  }

  public function getObjectPolicyName() {
    return pht('Reviewers');
  }

  public function getPolicyExplanation() {
    return pht('Reviewers can take this action.');
  }

  public function getRuleDescription() {
    return pht('reviewers');
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof DifferentialRevision);
  }

  public function willApplyRules(
    PhabricatorUser $viewer,
    array $values,
    array $objects) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return;
    }

    if (empty($this->reviewing[$viewer_phid])) {
      $this->reviewing[$viewer_phid] = array();
    }

    if (!isset($this->sourcePHIDs[$viewer_phid])) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withMemberPHIDs(array($viewer_phid))
        ->execute();

      $source_phids = mpull($projects, 'getPHID');
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
          $this->reviewing[$viewer_phid][$object->getPHID()] = true;
          break;
        }
      }
    }

    $phids = mpull($objects, 'getPHID');
    if (!$phids) {
      return;
    }

    $reviewers = id(new DifferentialReviewer())->loadAllWhere(
      'revisionPHID IN (%Ls) AND reviewerPHID IN (%Ls)',
      $phids,
      $this->sourcePHIDs[$viewer_phid]);
    if (!$reviewers) {
      return;
    }

    $revision_phids = mpull($reviewers, 'getRevisionPHID');
    $this->reviewing[$viewer_phid] += array_fill_keys($revision_phids, true);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    $reviewing = idx($this->reviewing, $viewer_phid, array());
    return isset($reviewing[$object->getPHID()]);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }
}
