<?php

final class HeraldDifferentialRevisionAdapter
  extends HeraldDifferentialAdapter {

  protected $revision;

  protected $explicitCCs;
  protected $explicitReviewers;
  protected $forbiddenCCs;

  protected $newCCs = array();
  protected $remCCs = array();
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

  public function getObject() {
    return $this->revision;
  }

  public function getDiff() {
    return $this->diff;
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

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_TITLE,
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_AUTHOR_PROJECTS,
        self::FIELD_REVIEWERS,
        self::FIELD_CC,
        self::FIELD_REPOSITORY,
        self::FIELD_REPOSITORY_PROJECTS,
        self::FIELD_DIFF_FILE,
        self::FIELD_DIFF_CONTENT,
        self::FIELD_DIFF_ADDED_CONTENT,
        self::FIELD_DIFF_REMOVED_CONTENT,
        self::FIELD_AFFECTED_PACKAGE,
        self::FIELD_AFFECTED_PACKAGE_OWNER,
        self::FIELD_IS_NEW_OBJECT,
        self::FIELD_ARCANIST_PROJECT,
      ),
      parent::getFields());
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
    $object->diff = $diff;

    return $object;
  }

  public function setExplicitCCs($explicit_ccs) {
    $this->explicitCCs = $explicit_ccs;
    return $this;
  }

  public function setExplicitReviewers($explicit_reviewers) {
    $this->explicitReviewers = $explicit_reviewers;
    return $this;
  }

  public function setForbiddenCCs($forbidden_ccs) {
    $this->forbiddenCCs = $forbidden_ccs;
    return $this;
  }

  public function getCCsAddedByHerald() {
    return array_diff_key($this->newCCs, $this->remCCs);
  }

  public function getCCsRemovedByHerald() {
    return $this->remCCs;
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

  public function getPHID() {
    return $this->revision->getPHID();
  }

  public function getHeraldName() {
    return $this->revision->getTitle();
  }

  protected function loadChangesets() {
    if ($this->changesets === null) {
      $this->changesets = $this->diff->loadChangesets();
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

  public function getHeraldField($field) {
    switch ($field) {
      case self::FIELD_TITLE:
        return $this->revision->getTitle();
        break;
      case self::FIELD_BODY:
        return $this->revision->getSummary()."\n".
               $this->revision->getTestPlan();
        break;
      case self::FIELD_AUTHOR:
        return $this->revision->getAuthorPHID();
        break;
      case self::FIELD_AUTHOR_PROJECTS:
        $author_phid = $this->revision->getAuthorPHID();
        if (!$author_phid) {
          return array();
        }

        $projects = id(new PhabricatorProjectQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withMemberPHIDs(array($author_phid))
          ->execute();

        return mpull($projects, 'getPHID');
      case self::FIELD_DIFF_FILE:
        return $this->loadAffectedPaths();
      case self::FIELD_CC:
        if (isset($this->explicitCCs)) {
          return array_keys($this->explicitCCs);
        } else {
          return $this->revision->getCCPHIDs();
        }
      case self::FIELD_REVIEWERS:
        if (isset($this->explicitReviewers)) {
          return array_keys($this->explicitReviewers);
        } else {
          return $this->revision->getReviewers();
        }
      case self::FIELD_REPOSITORY:
        $repository = $this->loadRepository();
        if (!$repository) {
          return null;
        }
        return $repository->getPHID();
      case self::FIELD_REPOSITORY_PROJECTS:
        $repository = $this->loadRepository();
        if (!$repository) {
          return array();
        }
        return $repository->getProjectPHIDs();
      case self::FIELD_DIFF_CONTENT:
        return $this->loadContentDictionary();
      case self::FIELD_DIFF_ADDED_CONTENT:
        return $this->loadAddedContentDictionary();
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return $this->loadRemovedContentDictionary();
      case self::FIELD_AFFECTED_PACKAGE:
        $packages = $this->loadAffectedPackages();
        return mpull($packages, 'getPHID');
      case self::FIELD_AFFECTED_PACKAGE_OWNER:
        $packages = $this->loadAffectedPackages();
        return PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
          mpull($packages, 'getID'));
      case self::FIELD_ARCANIST_PROJECT:
        return $this->revision->getArcanistProjectPHID();
    }

    return parent::getHeraldField($field);
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
    if ($this->explicitCCs) {
      $effect = new HeraldEffect();
      $effect->setAction(self::ACTION_ADD_CC);
      $effect->setTarget(array_keys($this->explicitCCs));
      $effect->setReason(
        pht('CCs provided explicitly by revision author or carried over '.
        'from a previous version of the revision.'));
      $result[] = new HeraldApplyTranscript(
        $effect,
        true,
        pht('Added addresses to CC list.'));
    }

    $forbidden_ccs = array_fill_keys(
      nonempty($this->forbiddenCCs, array()),
      true);

    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('OK, did nothing.'));
          break;
        case self::ACTION_ADD_CC:
          $base_target = $effect->getTarget();
          $forbidden = array();
          foreach ($base_target as $key => $fbid) {
            if (isset($forbidden_ccs[$fbid])) {
              $forbidden[] = $fbid;
              unset($base_target[$key]);
            } else {
              $this->newCCs[$fbid] = true;
            }
          }

          if ($forbidden) {
            $failed = clone $effect;
            $failed->setTarget($forbidden);
            if ($base_target) {
              $effect->setTarget($base_target);
              $result[] = new HeraldApplyTranscript(
                $effect,
                true,
                pht('Added these addresses to CC list. '.
                'Others could not be added.'));
            }
            $result[] = new HeraldApplyTranscript(
              $failed,
              false,
              pht('CC forbidden, these addresses have unsubscribed.'));
          } else {
            $result[] = new HeraldApplyTranscript(
              $effect,
              true,
              pht('Added addresses to CC list.'));
          }
          break;
        case self::ACTION_REMOVE_CC:
          foreach ($effect->getTarget() as $fbid) {
            $this->remCCs[$fbid] = true;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Removed addresses from CC list.'));
          break;
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
