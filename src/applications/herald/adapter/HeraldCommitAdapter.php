<?php

/**
 * @group herald
 */
final class HeraldCommitAdapter extends HeraldAdapter {

  const FIELD_NEED_AUDIT_FOR_PACKAGE = 'need-audit-for-package';
  const FIELD_DIFFERENTIAL_REVISION  = 'differential-revision';
  const FIELD_DIFFERENTIAL_REVIEWERS = 'differential-reviewers';
  const FIELD_DIFFERENTIAL_CCS       = 'differential-ccs';

  protected $diff;
  protected $revision;

  protected $repository;
  protected $commit;
  protected $commitData;

  protected $emailPHIDs = array();
  protected $addCCPHIDs = array();
  protected $auditMap = array();

  protected $affectedPaths;
  protected $affectedRevision;
  protected $affectedPackages;
  protected $auditNeededPackages;

  public function isEnabled() {
    $app = 'PhabricatorApplicationDiffusion';
    return PhabricatorApplication::isClassInstalled($app);
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
      self::FIELD_DIFFERENTIAL_REVISION => pht('Differential revision'),
      self::FIELD_DIFFERENTIAL_REVIEWERS => pht('Differential reviewers'),
      self::FIELD_DIFFERENTIAL_CCS => pht('Differential CCs'),
    ) + parent::getFieldNameMap();
  }

  public function getFields() {
    return array(
      self::FIELD_BODY,
      self::FIELD_AUTHOR,
      self::FIELD_COMMITTER,
      self::FIELD_REVIEWER,
      self::FIELD_REPOSITORY,
      self::FIELD_DIFF_FILE,
      self::FIELD_DIFF_CONTENT,
      self::FIELD_RULE,
      self::FIELD_AFFECTED_PACKAGE,
      self::FIELD_AFFECTED_PACKAGE_OWNER,
      self::FIELD_NEED_AUDIT_FOR_PACKAGE,
      self::FIELD_DIFFERENTIAL_REVISION,
      self::FIELD_DIFFERENTIAL_REVIEWERS,
      self::FIELD_DIFFERENTIAL_CCS,
    );
  }

  public function getConditionsForField($field) {
    switch ($field) {
      case self::FIELD_DIFFERENTIAL_REVIEWERS:
      case self::FIELD_DIFFERENTIAL_CCS:
        return array(
          self::CONDITION_INCLUDE_ALL,
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
        );
      case self::FIELD_DIFFERENTIAL_REVISION:
        return array(
          self::CONDITION_EXISTS,
          self::CONDITION_NOT_EXISTS,
        );
      case self::FIELD_NEED_AUDIT_FOR_PACKAGE:
        return array(
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
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
          self::ACTION_NOTHING,
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
        $revision = id(new DifferentialRevision())->load($revision_id);
        if ($revision) {
          $revision->loadRelationships();
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
        try {
          $diff = $this->loadCommitDiff();
        } catch (Exception $ex) {
          return array(
            '<<< Failed to load diff, this may mean the change was '.
            'unimaginably enormous. >>>');
        }
        $dict = array();
        $lines = array();
        $changes = $diff->getChangesets();
        foreach ($changes as $change) {
          $lines = array();
          foreach ($change->getHunks() as $hunk) {
            $lines[] = $hunk->makeChanges();
          }
          $dict[$change->getFilename()] = implode("\n", $lines);
        }
        return $dict;
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
