<?php

final class HeraldCommitAdapter
  extends HeraldAdapter
  implements HarbormasterBuildableAdapterInterface {

  protected $diff;
  protected $revision;

  protected $repository;
  protected $commit;
  protected $commitData;
  private $commitDiff;

  protected $affectedPaths;
  protected $affectedRevision;
  protected $affectedPackages;
  protected $auditNeededPackages;

  private $buildRequests = array();

  public function getAdapterApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newObject() {
    return new PhabricatorRepositoryCommit();
  }

  protected function initializeNewAdapter() {
    $this->commit = $this->newObject();
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

  public function loadAuditNeededPackages() {
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
      $this->auditNeededPackages = $requests;
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

  public function isDiffEnormous() {
    $this->loadDiffContent('*');
    return ($this->commitDiff instanceof Exception);
  }

  public function loadDiffContent($type) {
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


/* -(  HarbormasterBuildableAdapterInterface  )------------------------------ */


  public function getHarbormasterBuildablePHID() {
    return $this->getObject()->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getObject()->getRepository()->getPHID();
  }

  public function getQueuedHarbormasterBuildRequests() {
    return $this->buildRequests;
  }

  public function queueHarbormasterBuildRequest(
    HarbormasterBuildRequest $request) {
    $this->buildRequests[] = $request;
  }

}
