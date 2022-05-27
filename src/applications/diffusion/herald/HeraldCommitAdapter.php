<?php

final class HeraldCommitAdapter
  extends HeraldAdapter
  implements HarbormasterBuildableAdapterInterface {

  protected $diff;
  protected $revision;

  protected $commit;
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

  public function isTestAdapterForObject($object) {
    return ($object instanceof PhabricatorRepositoryCommit);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run after a commit is discovered and imported.');
  }

  public function newTestAdapter(PhabricatorUser $viewer, $object) {
    return id(clone $this)
      ->setObject($object);
  }

  protected function initializeNewAdapter() {
    $this->commit = $this->newObject();
  }

  public function setObject($object) {
    $viewer = $this->getViewer();
    $commit_phid = $object->getPHID();

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($commit_phid))
      ->needCommitData(true)
      ->needIdentities(true)
      ->needAuditRequests(true)
      ->executeOne();
    if (!$commit) {
      throw new Exception(
        pht(
          'Failed to reload commit ("%s") to fetch commit data.',
          $commit_phid));
    }

    $this->commit = $commit;

    return $this;
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
    $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

    $repository_phid = $this->getRepository()->getPHID();
    $commit_phid = $this->getObject()->getPHID();

    $phids = array();
    $phids[] = $commit_phid;
    $phids[] = $repository_phid;

    // NOTE: This is projects for the repository, not for the commit. When
    // Herald evaluates, commits normally can not have any project tags yet.
    $repository_project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $repository_phid,
      $project_type);
    foreach ($repository_project_phids as $phid) {
      $phids[] = $phid;
    }

    $phids = array_unique($phids);
    $phids = array_values($phids);

    return $phids;
  }

  public function explainValidTriggerObjects() {
    return pht('This rule can trigger for **repositories** and **projects**.');
  }

  public function getHeraldName() {
    return $this->commit->getMonogram();
  }

  public function loadAffectedPaths() {
    $viewer = $this->getViewer();

    if ($this->affectedPaths === null) {
      $result = PhabricatorOwnerPathQuery::loadAffectedPaths(
        $this->getRepository(),
        $this->commit,
        $viewer);
      $this->affectedPaths = $result;
    }

    return $this->affectedPaths;
  }

  public function loadAffectedPackages() {
    if ($this->affectedPackages === null) {
      $packages = PhabricatorOwnersPackage::loadAffectedPackages(
        $this->getRepository(),
        $this->loadAffectedPaths());
      $this->affectedPackages = $packages;
    }
    return $this->affectedPackages;
  }

  public function loadAuditNeededPackages() {
    if ($this->auditNeededPackages === null) {
      $status_arr = array(
        PhabricatorAuditRequestStatus::AUDIT_REQUIRED,
        PhabricatorAuditRequestStatus::CONCERNED,
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
      $viewer = $this->getViewer();

      // NOTE: The viewer here is omnipotent, which means that Herald discloses
      // some information users do not normally have access to when rules load
      // the revision related to a commit. See D20468.

      // A user who wants to learn about "Dxyz" can write a Herald rule which
      // uses all the "Related revision..." fields, then push a commit which
      // contains "Differential Revision: Dxyz" in the message to make Herald
      // evaluate the commit with "Dxyz" as the related revision.

      // At time of writing, this commit will link to the revision and the
      // transcript for the commit will disclose some information about the
      // revision (like reviewers, subscribers, and build status) which the
      // commit author could not otherwise see.

      // For now, we just accept this. The disclosures are relatively
      // uninteresting and you have to jump through a lot of hoops (and leave
      // a lot of evidence) to get this information.

      $revision = DiffusionCommitRevisionQuery::loadRevisionForCommit(
        $viewer,
        $this->getObject());
      if ($revision) {
        $this->affectedRevision = $revision;
      } else {
        $this->affectedRevision = false;
      }
    }

    return $this->affectedRevision;
  }

  public static function getEnormousByteLimit() {
    return 256 * 1024 * 1024; // 256MB. See T13142 and T13143.
  }

  public static function getEnormousTimeLimit() {
    return 60 * 15; // 15 Minutes
  }

  private function loadCommitDiff() {
    $viewer = $this->getViewer();

    $byte_limit = self::getEnormousByteLimit();
    $time_limit = self::getEnormousTimeLimit();

    $diff_info = $this->callConduit(
      'diffusion.rawdiffquery',
      array(
        'commit' => $this->commit->getCommitIdentifier(),
        'timeout' => $time_limit,
        'byteLimit' => $byte_limit,
        'linesOfContext' => 0,
      ));

    if ($diff_info['tooHuge']) {
      throw new Exception(
        pht(
          'The raw text of this change is enormous (larger than %s byte(s)). '.
          'Herald can not process it.',
          new PhutilNumber($byte_limit)));
    }

    if ($diff_info['tooSlow']) {
      throw new Exception(
        pht(
          'The raw text of this change took too long to process (longer '.
          'than %s second(s)). Herald can not process it.',
          new PhutilNumber($time_limit)));
    }

    $file_phid = $diff_info['filePHID'];
    $diff_file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$diff_file) {
      throw new Exception(
        pht(
          'Failed to load diff ("%s") for this change.',
          $file_phid));
    }

    $raw = $diff_file->loadFileData();

    // See T13667. This happens when a commit is empty and affects no files.
    if (!strlen($raw)) {
      return false;
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

    if ($this->commitDiff === false) {
      return array();
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

  public function loadIsMergeCommit() {
    $parents = $this->callConduit(
      'diffusion.commitparentsquery',
      array(
        'commit' => $this->getObject()->getCommitIdentifier(),
      ));

    return (count($parents) > 1);
  }

  private function callConduit($method, array $params) {
    $viewer = $this->getViewer();

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $viewer,
        'repository' => $this->getRepository(),
        'commit' => $this->commit->getCommitIdentifier(),
      ));

    return DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      $method,
      $params);
  }

  private function getRepository() {
    return $this->getObject()->getRepository();
  }

  public function getAuthorPHID() {
    return $this->getObject()->getEffectiveAuthorPHID();
  }

  public function getCommitterPHID() {
    $commit = $this->getObject();

    if ($commit->hasCommitterIdentity()) {
      $identity = $commit->getCommitterIdentity();
      return $identity->getCurrentEffectiveUserPHID();
    }

    return null;
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
