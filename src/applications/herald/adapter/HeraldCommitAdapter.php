<?php

/**
 * @group herald
 */
final class HeraldCommitAdapter extends HeraldAdapter {

  const FIELD_NEED_AUDIT_FOR_PACKAGE      = 'need-audit-for-package';
  const FIELD_REPOSITORY_AUTOCLOSE_BRANCH = 'repository-autoclose-branch';

  protected $diff;
  protected $revision;

  protected $repository;
  protected $commit;
  protected $commitData;
  private $commitDiff;

  protected $emailPHIDs = array();
  protected $addCCPHIDs = array();
  protected $auditMap = array();
  protected $buildPlans = array();

  protected $affectedPaths;
  protected $affectedRevision;
  protected $affectedPackages;
  protected $auditNeededPackages;

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

  public function getObject() {
    return $this->commit;
  }

  public function getAdapterContentType() {
    return 'commit';
  }

  public function getAdapterContentName() {
    return pht('Commits');
  }

  public function getFieldNameMap() {
    return array(
      self::FIELD_NEED_AUDIT_FOR_PACKAGE =>
        pht('Affected packages that need audit'),
      self::FIELD_REPOSITORY_AUTOCLOSE_BRANCH => pht('On autoclose branch'),
    ) + parent::getFieldNameMap();
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_COMMITTER,
        self::FIELD_REVIEWER,
        self::FIELD_REPOSITORY,
        self::FIELD_DIFF_FILE,
        self::FIELD_DIFF_CONTENT,
        self::FIELD_DIFF_ADDED_CONTENT,
        self::FIELD_DIFF_REMOVED_CONTENT,
        self::FIELD_RULE,
        self::FIELD_AFFECTED_PACKAGE,
        self::FIELD_AFFECTED_PACKAGE_OWNER,
        self::FIELD_NEED_AUDIT_FOR_PACKAGE,
        self::FIELD_DIFFERENTIAL_REVISION,
        self::FIELD_DIFFERENTIAL_ACCEPTED,
        self::FIELD_DIFFERENTIAL_REVIEWERS,
        self::FIELD_DIFFERENTIAL_CCS,
        self::FIELD_REPOSITORY_AUTOCLOSE_BRANCH,
      ),
      parent::getFields());
  }

  public function getConditionsForField($field) {
    switch ($field) {
      case self::FIELD_NEED_AUDIT_FOR_PACKAGE:
        return array(
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
        );
      case self::FIELD_REPOSITORY_AUTOCLOSE_BRANCH:
        return array(
          self::CONDITION_UNCONDITIONALLY,
        );
    }
    return parent::getConditionsForField($field);
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_EMAIL,
          self::ACTION_AUDIT,
          self::ACTION_APPLY_BUILD_PLANS,
          self::ACTION_NOTHING
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_EMAIL,
          self::ACTION_FLAG,
          self::ACTION_AUDIT,
          self::ACTION_NOTHING,
        );
    }
  }

  public function getValueTypeForFieldAndCondition($field, $condition) {
    switch ($field) {
      case self::FIELD_DIFFERENTIAL_CCS:
        return self::VALUE_EMAIL;
      case self::FIELD_NEED_AUDIT_FOR_PACKAGE:
        return self::VALUE_OWNERS_PACKAGE;
    }

    return parent::getValueTypeForFieldAndCondition($field, $condition);
  }

  public static function newLegacyAdapter(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $commit_data) {

    $object = new HeraldCommitAdapter();

    $commit->attachRepository($repository);

    $object->repository = $repository;
    $object->commit = $commit;
    $object->commitData = $commit_data;

    return $object;
  }

  public function getPHID() {
    return $this->commit->getPHID();
  }

  public function getEmailPHIDs() {
    return array_keys($this->emailPHIDs);
  }

  public function getAddCCMap() {
    return $this->addCCPHIDs;
  }

  public function getAuditMap() {
    return $this->auditMap;
  }

  public function getBuildPlans() {
    return $this->buildPlans;
  }

  public function getHeraldName() {
    return
      'r'.
      $this->repository->getCallsign().
      $this->commit->getCommitIdentifier();
  }

  public function loadAffectedPaths() {
    if ($this->affectedPaths === null) {
      $result = PhabricatorOwnerPathQuery::loadAffectedPaths(
        $this->repository,
        $this->commit,
        PhabricatorUser::getOmnipotentUser());
      $this->affectedPaths = $result;
    }
    return $this->affectedPaths;
  }

  public function loadAffectedPackages() {
    if ($this->affectedPackages === null) {
      $packages = PhabricatorOwnersPackage::loadAffectedPackages(
        $this->repository,
        $this->loadAffectedPaths());
      $this->affectedPackages = $packages;
    }
    return $this->affectedPackages;
  }

  public function loadAuditNeededPackage() {
    if ($this->auditNeededPackages === null) {
      $status_arr = array(
        PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
        PhabricatorAuditStatusConstants::CONCERNED,
      );
      $requests = id(new PhabricatorRepositoryAuditRequest())
          ->loadAllWhere(
        "commitPHID = %s AND auditStatus IN (%Ls)",
        $this->commit->getPHID(),
        $status_arr);

      $packages = mpull($requests, 'getAuditorPHID');
      $this->auditNeededPackages = $packages;
    }
    return $this->auditNeededPackages;
  }

  public function loadDifferentialRevision() {
    if ($this->affectedRevision === null) {
      $this->affectedRevision = false;
      $data = $this->commitData;
      $revision_id = $data->getCommitDetail('differential.revisionID');
      if ($revision_id) {
        // NOTE: The Herald rule owner might not actually have access to
        // the revision, and can control which revision a commit is
        // associated with by putting text in the commit message. However,
        // the rules they can write against revisions don't actually expose
        // anything interesting, so it seems reasonable to load unconditionally
        // here.

        $revision = id(new DifferentialRevisionQuery())
          ->withIDs(array($revision_id))
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->needRelationships(true)
          ->needReviewerStatus(true)
          ->executeOne();
        if ($revision) {
          $this->affectedRevision = $revision;
        }
      }
    }
    return $this->affectedRevision;
  }

  private function loadCommitDiff() {
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => PhabricatorUser::getOmnipotentUser(),
        'repository' => $this->repository,
        'commit' => $this->commit->getCommitIdentifier(),
      ));

    $raw = DiffusionQuery::callConduitWithDiffusionRequest(
      PhabricatorUser::getOmnipotentUser(),
      $drequest,
      'diffusion.rawdiffquery',
      array(
        'commit' => $this->commit->getCommitIdentifier(),
        'timeout' => 60 * 60 * 15,
        'linesOfContext' => 0));

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($raw);

    $diff = DifferentialDiff::newFromRawChanges($changes);
    return $diff;
  }

  private function getDiffContent($type) {
    if ($this->commitDiff === null) {
      try {
        $this->commitDiff = $this->loadCommitDiff();
      } catch (Exception $ex) {
        $this->commitDiff = $ex;
        phlog($ex);
      }
    }

    if ($this->commitDiff instanceof Exception) {
      $ex = $this->commitDiff;
      $ex_class = get_class($ex);
      $ex_message = pht('Failed to load changes: %s', $ex->getMessage());

      return array(
        '<'.$ex_class.'>' => $ex_message,
      );
    }

    $changes = $this->commitDiff->getChangesets();

    $result = array();
    foreach ($changes as $change) {
      $lines = array();
      foreach ($change->getHunks() as $hunk) {
        switch ($type) {
          case '-':
            $lines[] = $hunk->makeOldFile();
            break;
          case '+':
            $lines[] = $hunk->makeNewFile();
            break;
          case '*':
            $lines[] = $hunk->makeChanges();
            break;
          default:
            throw new Exception("Unknown content selection '{$type}'!");
        }
      }
      $result[$change->getFilename()] = implode("\n", $lines);
    }

    return $result;
  }

  public function getHeraldField($field) {
    $data = $this->commitData;
    switch ($field) {
      case self::FIELD_BODY:
        return $data->getCommitMessage();
      case self::FIELD_AUTHOR:
        return $data->getCommitDetail('authorPHID');
      case self::FIELD_COMMITTER:
        return $data->getCommitDetail('committerPHID');
      case self::FIELD_REVIEWER:
        return $data->getCommitDetail('reviewerPHID');
      case self::FIELD_DIFF_FILE:
        return $this->loadAffectedPaths();
      case self::FIELD_REPOSITORY:
        return $this->repository->getPHID();
      case self::FIELD_DIFF_CONTENT:
        return $this->getDiffContent('*');
      case self::FIELD_DIFF_ADDED_CONTENT:
        return $this->getDiffContent('+');
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return $this->getDiffContent('-');
      case self::FIELD_AFFECTED_PACKAGE:
        $packages = $this->loadAffectedPackages();
        return mpull($packages, 'getPHID');
      case self::FIELD_AFFECTED_PACKAGE_OWNER:
        $packages = $this->loadAffectedPackages();
        $owners = PhabricatorOwnersOwner::loadAllForPackages($packages);
        return mpull($owners, 'getUserPHID');
      case self::FIELD_NEED_AUDIT_FOR_PACKAGE:
        return $this->loadAuditNeededPackage();
      case self::FIELD_DIFFERENTIAL_REVISION:
        $revision = $this->loadDifferentialRevision();
        if (!$revision) {
          return null;
        }
        return $revision->getID();
      case self::FIELD_DIFFERENTIAL_ACCEPTED:
        $revision = $this->loadDifferentialRevision();
        if (!$revision) {
          return null;
        }
        $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
        if ($revision->getStatus() != $status_accepted) {
          return null;
        }
        return $revision->getPHID();
      case self::FIELD_DIFFERENTIAL_REVIEWERS:
        $revision = $this->loadDifferentialRevision();
        if (!$revision) {
          return array();
        }
        return $revision->getReviewers();
      case self::FIELD_DIFFERENTIAL_CCS:
        $revision = $this->loadDifferentialRevision();
        if (!$revision) {
          return array();
        }
        return $revision->getCCPHIDs();
      case self::FIELD_REPOSITORY_AUTOCLOSE_BRANCH:
        return $this->repository->shouldAutocloseCommit(
          $this->commit,
          $this->commitData);
    }

    return parent::getHeraldField($field);
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Great success at doing nothing.'));
          break;
        case self::ACTION_EMAIL:
          foreach ($effect->getTarget() as $phid) {
            $this->emailPHIDs[$phid] = true;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added address to email targets.'));
          break;
        case self::ACTION_ADD_CC:
          foreach ($effect->getTarget() as $phid) {
            if (empty($this->addCCPHIDs[$phid])) {
              $this->addCCPHIDs[$phid] = array();
            }
            $this->addCCPHIDs[$phid][] = $effect->getRuleID();
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added address to CC.'));
          break;
        case self::ACTION_AUDIT:
          foreach ($effect->getTarget() as $phid) {
            if (empty($this->auditMap[$phid])) {
              $this->auditMap[$phid] = array();
            }
            $this->auditMap[$phid][] = $effect->getRuleID();
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Triggered an audit.'));
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
        case self::ACTION_FLAG:
          $result[] = parent::applyFlagEffect(
            $effect,
            $this->commit->getPHID());
          break;
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
