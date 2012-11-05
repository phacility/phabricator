<?php

final class HeraldDifferentialRevisionAdapter extends HeraldObjectAdapter {

  protected $revision;
  protected $diff;

  protected $explicitCCs;
  protected $explicitReviewers;
  protected $forbiddenCCs;

  protected $newCCs = array();
  protected $remCCs = array();
  protected $emailPHIDs = array();

  protected $repository;
  protected $affectedPackages;
  protected $changesets;

  public function __construct(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $revision->loadRelationships();
    $this->revision = $revision;
    $this->diff = $diff;
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

  public function getPHID() {
    return $this->revision->getPHID();
  }

  public function getHeraldName() {
    return $this->revision->getTitle();
  }

  public function getHeraldTypeName() {
    return HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL;
  }

  public function loadRepository() {
    if ($this->repository === null) {
      $diff = $this->diff;

      $repository = false;

      if ($diff->getRepositoryUUID()) {
        $repository = id(new PhabricatorRepository())->loadOneWhere(
          'uuid = %s',
          $diff->getRepositoryUUID());
      }

      if (!$repository && $diff->getArcanistProjectPHID()) {
        $project = id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
          'phid = %s',
          $diff->getArcanistProjectPHID());
        if ($project && $project->getRepositoryID()) {
          $repository = id(new PhabricatorRepository())->load(
            $project->getRepositoryID());
        }
      }

      $this->repository = $repository;
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
      case HeraldFieldConfig::FIELD_TITLE:
        return $this->revision->getTitle();
        break;
      case HeraldFieldConfig::FIELD_BODY:
        return $this->revision->getSummary()."\n".
               $this->revision->getTestPlan();
        break;
      case HeraldFieldConfig::FIELD_AUTHOR:
        return $this->revision->getAuthorPHID();
        break;
      case HeraldFieldConfig::FIELD_DIFF_FILE:
        return $this->loadAffectedPaths();
      case HeraldFieldConfig::FIELD_CC:
        if (isset($this->explicitCCs)) {
          return array_keys($this->explicitCCs);
        } else {
          return $this->revision->getCCPHIDs();
        }
      case HeraldFieldConfig::FIELD_REVIEWERS:
        if (isset($this->explicitReviewers)) {
          return array_keys($this->explicitReviewers);
        } else {
          return $this->revision->getReviewers();
        }
      case HeraldFieldConfig::FIELD_REPOSITORY:
        $repository = $this->loadRepository();
        if (!$repository) {
          return null;
        }
        return $repository->getPHID();
      case HeraldFieldConfig::FIELD_DIFF_CONTENT:
        return $this->loadContentDictionary();
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE:
        $packages = $this->loadAffectedPackages();
        return mpull($packages, 'getPHID');
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE_OWNER:
        $packages = $this->loadAffectedPackages();
        return PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
          mpull($packages, 'getID'));
      default:
        throw new Exception("Invalid field '{$field}'.");
    }
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    if ($this->explicitCCs) {
      $effect = new HeraldEffect();
      $effect->setAction(HeraldActionConfig::ACTION_ADD_CC);
      $effect->setTarget(array_keys($this->explicitCCs));
      $effect->setReason(
        'CCs provided explicitly by revision author or carried over from a '.
        'previous version of the revision.');
      $result[] = new HeraldApplyTranscript(
        $effect,
        true,
        'Added addresses to CC list.');
    }

    $forbidden_ccs = array_fill_keys(
      nonempty($this->forbiddenCCs, array()),
      true);

    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case HeraldActionConfig::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            'OK, did nothing.');
          break;
        case HeraldActionConfig::ACTION_FLAG:
          $result[] = parent::applyFlagEffect(
            $effect,
            $this->revision->getPHID());
          break;
        case HeraldActionConfig::ACTION_EMAIL:
        case HeraldActionConfig::ACTION_ADD_CC:
          $op = ($action == HeraldActionConfig::ACTION_EMAIL) ? 'email' : 'CC';
          $base_target = $effect->getTarget();
          $forbidden = array();
          foreach ($base_target as $key => $fbid) {
            if (isset($forbidden_ccs[$fbid])) {
              $forbidden[] = $fbid;
              unset($base_target[$key]);
            } else {
              if ($action == HeraldActionConfig::ACTION_EMAIL) {
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
                'Added these addresses to '.$op.' list. '.
                'Others could not be added.');
            }
            $result[] = new HeraldApplyTranscript(
              $failed,
              false,
              $op.' forbidden, these addresses have unsubscribed.');
          } else {
            $result[] = new HeraldApplyTranscript(
              $effect,
              true,
              'Added addresses to '.$op.' list.');
          }
          break;
        case HeraldActionConfig::ACTION_REMOVE_CC:
          foreach ($effect->getTarget() as $fbid) {
            $this->remCCs[$fbid] = true;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            'Removed addresses from CC list.');
          break;
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
