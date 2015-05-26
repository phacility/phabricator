<?php

final class HeraldCommitAdapter extends HeraldAdapter {

  const FIELD_NEED_AUDIT_FOR_PACKAGE      = 'need-audit-for-package';
  const FIELD_REPOSITORY_AUTOCLOSE_BRANCH = 'repository-autoclose-branch';

  protected $diff;
  protected $revision;

  protected $repository;
  protected $commit;
  protected $commitData;
  private $commitDiff;

  protected $addCCPHIDs = array();
  protected $auditMap = array();
  protected $buildPlans = array();

  protected $affectedPaths;
  protected $affectedRevision;
  protected $affectedPackages;
  protected $auditNeededPackages;

  public function getAdapterApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newObject() {
    return new PhabricatorRepositoryCommit();
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

  public function getAdapterContentDescription() {
    return pht(
      "React to new commits appearing in tracked repositories.\n".
      "Commit rules can send email, flag commits, trigger audits, ".
      "and run build plans.");
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
        return true;
      default:
        return false;
    }
  }

  public function canTriggerOnObject($object) {
    if ($object instanceof PhabricatorRepository) {
      return true;
    }
    if ($object instanceof PhabricatorProject) {
      return true;
    }
    return false;
  }

  public function getTriggerObjectPHIDs() {
    return array_merge(
      array(
        $this->repository->getPHID(),
        $this->getPHID(),
      ),
      $this->repository->getProjectPHIDs());
  }

  public function explainValidTriggerObjects() {
    return pht('This rule can trigger for **repositories** and **projects**.');
  }

  public function getFieldNameMap() {
    return array(
      self::FIELD_NEED_AUDIT_FOR_PACKAGE =>
        pht('Affected packages that need audit'),
      self::FIELD_REPOSITORY_AUTOCLOSE_BRANCH
        => pht('Commit is on closing branch'),
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
        self::FIELD_REPOSITORY_PROJECTS,
        self::FIELD_DIFF_FILE,
        self::FIELD_DIFF_CONTENT,
        self::FIELD_DIFF_ADDED_CONTENT,
        self::FIELD_DIFF_REMOVED_CONTENT,
        self::FIELD_DIFF_ENORMOUS,
        self::FIELD_AFFECTED_PACKAGE,
        self::FIELD_AFFECTED_PACKAGE_OWNER,
        self::FIELD_NEED_AUDIT_FOR_PACKAGE,
        self::FIELD_DIFFERENTIAL_REVISION,
        self::FIELD_DIFFERENTIAL_ACCEPTED,
        self::FIELD_DIFFERENTIAL_REVIEWERS,
        self::FIELD_DIFFERENTIAL_CCS,
        self::FIELD_BRANCHES,
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
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_EMAIL,
            self::ACTION_AUDIT,
            self::ACTION_APPLY_BUILD_PLANS,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_EMAIL,
            self::ACTION_FLAG,
            self::ACTION_AUDIT,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
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

  public function setCommit(PhabricatorRepositoryCommit $commit) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIDs(array($commit->getRepositoryID()))
      ->needProjectPHIDs(true)
      ->executeOne();
    if (!$repository) {
      throw new Exception(pht('Unable to load repository!'));
    }

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    if (!$data) {
      throw new Exception(pht('Unable to load commit data!'));
    }

    $this->commit = clone $commit;
    $this->commit->attachRepository($repository);
    $this->commit->attachCommitData($data);

    $this->repository = $repository;
    $this->commitData = $data;

    return $this;
  }

  public function getPHID() {
    return $this->commit->getPHID();
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
        'commitPHID = %s AND auditStatus IN (%Ls)',
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

  public static function getEnormousByteLimit() {
    return 1024 * 1024 * 1024; // 1GB
  }

  public static function getEnormousTimeLimit() {
    return 60 * 15; // 15 Minutes
  }

  private function loadCommitDiff() {
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => PhabricatorUser::getOmnipotentUser(),
        'repository' => $this->repository,
        'commit' => $this->commit->getCommitIdentifier(),
      ));

    $byte_limit = self::getEnormousByteLimit();

    $raw = DiffusionQuery::callConduitWithDiffusionRequest(
      PhabricatorUser::getOmnipotentUser(),
      $drequest,
      'diffusion.rawdiffquery',
      array(
        'commit' => $this->commit->getCommitIdentifier(),
        'timeout' => self::getEnormousTimeLimit(),
        'byteLimit' => $byte_limit,
        'linesOfContext' => 0,
      ));

    if (strlen($raw) >= $byte_limit) {
      throw new Exception(
        pht(
          'The raw text of this change is enormous (larger than %d bytes). '.
          'Herald can not process it.',
          $byte_limit));
    }

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($raw);

    $diff = DifferentialDiff::newEphemeralFromRawChanges(
      $changes);
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
            throw new Exception(pht("Unknown content selection '%s'!", $type));
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
      case self::FIELD_REPOSITORY_PROJECTS:
        return $this->repository->getProjectPHIDs();
      case self::FIELD_DIFF_CONTENT:
        return $this->getDiffContent('*');
      case self::FIELD_DIFF_ADDED_CONTENT:
        return $this->getDiffContent('+');
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return $this->getDiffContent('-');
      case self::FIELD_DIFF_ENORMOUS:
        $this->getDiffContent('*');
        return ($this->commitDiff instanceof Exception);
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

        $status = $data->getCommitDetail(
          'precommitRevisionStatus',
          $revision->getStatus());
        switch ($status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::CLOSED:
            return $revision->getPHID();
        }

        return null;
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
      case self::FIELD_BRANCHES:
        $params = array(
          'callsign' => $this->repository->getCallsign(),
          'contains' => $this->commit->getCommitIdentifier(),
        );

        $result = id(new ConduitCall('diffusion.branchquery', $params))
          ->setUser(PhabricatorUser::getOmnipotentUser())
          ->execute();

        $refs = DiffusionRepositoryRef::loadAllFromDictionaries($result);
        return mpull($refs, 'getShortName');
      case self::FIELD_REPOSITORY_AUTOCLOSE_BRANCH:
        return $this->repository->shouldAutocloseCommit($this->commit);
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
        case self::ACTION_ADD_CC:
          foreach ($effect->getTarget() as $phid) {
            if (empty($this->addCCPHIDs[$phid])) {
              $this->addCCPHIDs[$phid] = array();
            }
            $this->addCCPHIDs[$phid][] = $effect->getRule()->getID();
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
            $this->auditMap[$phid][] = $effect->getRule()->getID();
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
        default:
          $result[] = $this->applyStandardEffect($effect);
          break;
      }
    }
    return $result;
  }

}
