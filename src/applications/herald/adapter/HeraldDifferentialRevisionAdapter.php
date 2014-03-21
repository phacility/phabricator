<?php

final class HeraldDifferentialRevisionAdapter extends HeraldAdapter {

  protected $revision;
  protected $diff;

  protected $explicitCCs;
  protected $explicitReviewers;
  protected $forbiddenCCs;

  protected $newCCs = array();
  protected $remCCs = array();
  protected $emailPHIDs = array();
  protected $addReviewerPHIDs = array();
  protected $blockingReviewerPHIDs = array();
  protected $buildPlans = array();

  protected $repository;
  protected $affectedPackages;
  protected $changesets;

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationDifferential';
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

  public function getEmailPHIDsAddedByHerald() {
    return $this->emailPHIDs;
  }

  public function getReviewersAddedByHerald() {
    return $this->addReviewerPHIDs;
  }

  public function getBlockingReviewersAddedByHerald() {
    return $this->blockingReviewerPHIDs;
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

  public function loadRepository() {
    if ($this->repository === null) {
      $this->repository = false;
      $repository_phid = $this->getObject()->getRepositoryPHID();
      if ($repository_phid) {
        $repository = id(new PhabricatorRepositoryQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs(array($repository_phid))
          ->needProjectPHIDs(true)
          ->executeOne();
        if ($repository) {
          $this->repository = $repository;
        }
      }
    }

    return $this->repository;
  }

  protected function loadChangesets() {
    if ($this->changesets === null) {
      $this->changesets = $this->diff->loadChangesets();
    }
    return $this->changesets;
  }

  protected function loadAffectedPaths() {
    $changesets = $this->loadChangesets();

    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $this->getAbsoluteRepositoryPathForChangeset($changeset);
    }
    return $paths;
  }

  protected function getAbsoluteRepositoryPathForChangeset(
    DifferentialChangeset $changeset) {

    $repository = $this->loadRepository();
    if (!$repository) {
      return '/'.ltrim($changeset->getFilename(), '/');
    }

    $diff = $this->diff;

    return $changeset->getAbsoluteRepositoryPath($repository, $diff);
  }

  protected function loadContentDictionary() {
    $changesets = $this->loadChangesets();

    $hunks = array();
    if ($changesets) {
      $hunks = id(new DifferentialHunk())->loadAllWhere(
        'changesetID in (%Ld)',
        mpull($changesets, 'getID'));
    }

    $dict = array();
    $hunks = mgroup($hunks, 'getChangesetID');
    $changesets = mpull($changesets, null, 'getID');
    foreach ($changesets as $id => $changeset) {
      $path = $this->getAbsoluteRepositoryPathForChangeset($changeset);
      $content = array();
      foreach (idx($hunks, $id, array()) as $hunk) {
        $content[] = $hunk->makeChanges();
      }
      $dict[$path] = implode("\n", $content);
    }

    return $dict;
  }

  protected function loadAddedContentDictionary() {
    $changesets = $this->loadChangesets();

    $hunks = array();
    if ($changesets) {
      $hunks = id(new DifferentialHunk())->loadAllWhere(
        'changesetID in (%Ld)',
        mpull($changesets, 'getID'));
    }

    $dict = array();
    $hunks = mgroup($hunks, 'getChangesetID');
    $changesets = mpull($changesets, null, 'getID');
    foreach ($changesets as $id => $changeset) {
      $path = $this->getAbsoluteRepositoryPathForChangeset($changeset);
      $content = array();
      foreach (idx($hunks, $id, array()) as $hunk) {
        $content[] = implode('', $hunk->getAddedLines());
      }
      $dict[$path] = implode("\n", $content);
    }

    return $dict;
  }

  protected function loadRemovedContentDictionary() {
    $changesets = $this->loadChangesets();

    $hunks = array();
    if ($changesets) {
      $hunks = id(new DifferentialHunk())->loadAllWhere(
        'changesetID in (%Ld)',
        mpull($changesets, 'getID'));
    }

    $dict = array();
    $hunks = mgroup($hunks, 'getChangesetID');
    $changesets = mpull($changesets, null, 'getID');
    foreach ($changesets as $id => $changeset) {
      $path = $this->getAbsoluteRepositoryPathForChangeset($changeset);
      $content = array();
      foreach (idx($hunks, $id, array()) as $hunk) {
        $content[] = implode('', $hunk->getRemovedLines());
      }
      $dict[$path] = implode("\n", $content);
    }

    return $dict;
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
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_REMOVE_CC,
          self::ACTION_EMAIL,
          self::ACTION_ADD_REVIEWERS,
          self::ACTION_ADD_BLOCKING_REVIEWERS,
          self::ACTION_APPLY_BUILD_PLANS,
          self::ACTION_NOTHING,
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_REMOVE_CC,
          self::ACTION_EMAIL,
          self::ACTION_FLAG,
          self::ACTION_ADD_REVIEWERS,
          self::ACTION_ADD_BLOCKING_REVIEWERS,
          self::ACTION_NOTHING,
        );
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
        case self::ACTION_FLAG:
          $result[] = parent::applyFlagEffect(
            $effect,
            $this->revision->getPHID());
          break;
        case self::ACTION_EMAIL:
        case self::ACTION_ADD_CC:
          $op = ($action == self::ACTION_EMAIL) ? 'email' : 'CC';
          $base_target = $effect->getTarget();
          $forbidden = array();
          foreach ($base_target as $key => $fbid) {
            if (isset($forbidden_ccs[$fbid])) {
              $forbidden[] = $fbid;
              unset($base_target[$key]);
            } else {
              if ($action == self::ACTION_EMAIL) {
                $this->emailPHIDs[$fbid] = true;
              } else {
                $this->newCCs[$fbid] = true;
              }
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
                pht('Added these addresses to %s list. '.
                'Others could not be added.', $op));
            }
            $result[] = new HeraldApplyTranscript(
              $failed,
              false,
              pht('%s forbidden, these addresses have unsubscribed.', $op));
          } else {
            $result[] = new HeraldApplyTranscript(
              $effect,
              true,
              pht('Added addresses to %s list.', $op));
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
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
