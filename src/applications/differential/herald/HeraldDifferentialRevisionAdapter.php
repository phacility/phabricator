<?php

final class HeraldDifferentialRevisionAdapter
  extends HeraldDifferentialAdapter {

  protected $revision;

  protected $explicitReviewers;
  protected $addReviewerPHIDs = array();
  protected $blockingReviewerPHIDs = array();
  protected $buildPlans = array();
  protected $requiredSignatureDocumentPHIDs = array();

  protected $affectedPackages;
  protected $changesets;
  private $haveHunks;

  public function getAdapterApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function newObject() {
    return new DifferentialRevision();
  }

  protected function initializeNewAdapter() {
    $this->revision = $this->newObject();
  }

  public function getObject() {
    return $this->revision;
  }

  public function getAdapterContentType() {
    return 'differential';
  }

  public function getAdapterContentName() {
    return pht('Differential Revisions');
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to revisions being created or updated.\n".
      "Revision rules can send email, flag revisions, add reviewers, ".
      "and run build plans.");
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
      HeraldRepetitionPolicyConfig::FIRST,
    );
  }

  public static function newLegacyAdapter(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {
    $object = new HeraldDifferentialRevisionAdapter();

    // Reload the revision to pick up relationship information.
    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($revision->getID()))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->needRelationships(true)
      ->needReviewerStatus(true)
      ->executeOne();

    $object->revision = $revision;
    $object->setDiff($diff);

    return $object;
  }

  public function setExplicitReviewers($explicit_reviewers) {
    $this->explicitReviewers = $explicit_reviewers;
    return $this;
  }

  public function getReviewersAddedByHerald() {
    return $this->addReviewerPHIDs;
  }

  public function getBlockingReviewersAddedByHerald() {
    return $this->blockingReviewerPHIDs;
  }

  public function getRequiredSignatureDocumentPHIDs() {
    return $this->requiredSignatureDocumentPHIDs;
  }

  public function getBuildPlans() {
    return $this->buildPlans;
  }

  public function getHeraldName() {
    return $this->revision->getTitle();
  }

  protected function loadChangesets() {
    if ($this->changesets === null) {
      $this->changesets = $this->getDiff()->loadChangesets();
    }
    return $this->changesets;
  }

  protected function loadChangesetsWithHunks() {
    $changesets = $this->loadChangesets();

    if ($changesets && !$this->haveHunks) {
      $this->haveHunks = true;

      id(new DifferentialHunkQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withChangesets($changesets)
        ->needAttachToChangesets(true)
        ->execute();
    }

    return $changesets;
  }

  public function loadAffectedPackages() {
    if ($this->affectedPackages === null) {
      $this->affectedPackages = array();

      $repository = $this->loadRepository();
      if ($repository) {
        $packages = PhabricatorOwnersPackage::loadAffectedPackages(
          $repository,
          $this->loadAffectedPaths());
        $this->affectedPackages = $packages;
      }
    }
    return $this->affectedPackages;
  }

  public function loadReviewers() {
    // TODO: This can probably go away as I believe it's just a performance
    // optimization, just retaining it while modularizing fields to limit the
    // scope of that change.
    if (isset($this->explicitReviewers)) {
      return array_keys($this->explicitReviewers);
    } else {
      return $this->revision->getReviewers();
    }
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_REMOVE_CC,
            self::ACTION_EMAIL,
            self::ACTION_ADD_REVIEWERS,
            self::ACTION_ADD_BLOCKING_REVIEWERS,
            self::ACTION_APPLY_BUILD_PLANS,
            self::ACTION_REQUIRE_SIGNATURE,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_REMOVE_CC,
            self::ACTION_EMAIL,
            self::ACTION_FLAG,
            self::ACTION_ADD_REVIEWERS,
            self::ACTION_ADD_BLOCKING_REVIEWERS,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
    }
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();

    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_ADD_REVIEWERS:
          foreach ($effect->getTarget() as $phid) {
            $this->addReviewerPHIDs[$phid] = true;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added reviewers.'));
          break;
        case self::ACTION_ADD_BLOCKING_REVIEWERS:
          // This adds reviewers normally, it just also marks them blocking.
          foreach ($effect->getTarget() as $phid) {
            $this->addReviewerPHIDs[$phid] = true;
            $this->blockingReviewerPHIDs[$phid] = true;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added blocking reviewers.'));
          break;
        case self::ACTION_APPLY_BUILD_PLANS:
          foreach ($effect->getTarget() as $phid) {
            $this->buildPlans[] = $phid;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Applied build plans.'));
          break;
        case self::ACTION_REQUIRE_SIGNATURE:
          foreach ($effect->getTarget() as $phid) {
            $this->requiredSignatureDocumentPHIDs[] = $phid;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Required signatures.'));
          break;
        default:
          $result[] = $this->applyStandardEffect($effect);
          break;
      }
    }
    return $result;
  }

}
