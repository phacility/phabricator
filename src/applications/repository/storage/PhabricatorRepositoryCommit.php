<?php

final class PhabricatorRepositoryCommit
  extends PhabricatorRepositoryDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorProjectInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorSubscribableInterface,
    PhabricatorMentionableInterface,
    HarbormasterBuildableInterface,
    HarbormasterCircleCIBuildableInterface,
    HarbormasterBuildkiteBuildableInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorTimelineInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface,
    PhabricatorConduitResultInterface,
    PhabricatorDraftInterface {

  protected $repositoryID;
  protected $phid;
  protected $authorIdentityPHID;
  protected $committerIdentityPHID;
  protected $commitIdentifier;
  protected $epoch;
  protected $authorPHID;
  protected $auditStatus = DiffusionCommitAuditStatus::NONE;
  protected $summary = '';
  protected $importStatus = 0;

  const IMPORTED_MESSAGE = 1;
  const IMPORTED_CHANGE = 2;
  const IMPORTED_PUBLISH = 8;
  const IMPORTED_ALL = 11;

  const IMPORTED_PERMANENT = 1024;
  const IMPORTED_UNREACHABLE = 2048;

  private $commitData = self::ATTACHABLE;
  private $audits = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $authorIdentity = self::ATTACHABLE;
  private $committerIdentity = self::ATTACHABLE;

  private $drafts = array();
  private $auditAuthorityPHIDs = array();

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository($assert_attached = true) {
    if ($assert_attached) {
      return $this->assertAttached($this->repository);
    }
    return $this->repository;
  }

  public function isPartiallyImported($mask) {
    return (($mask & $this->getImportStatus()) == $mask);
  }

  public function isImported() {
    return $this->isPartiallyImported(self::IMPORTED_ALL);
  }

  public function isUnreachable() {
    return $this->isPartiallyImported(self::IMPORTED_UNREACHABLE);
  }

  public function writeImportStatusFlag($flag) {
    return $this->adjustImportStatusFlag($flag, true);
  }

  public function clearImportStatusFlag($flag) {
    return $this->adjustImportStatusFlag($flag, false);
  }

  private function adjustImportStatusFlag($flag, $set) {
    $conn_w = $this->establishConnection('w');
    $table_name = $this->getTableName();
    $id = $this->getID();

    if ($set) {
      queryfx(
        $conn_w,
        'UPDATE %T SET importStatus = (importStatus | %d) WHERE id = %d',
        $table_name,
        $flag,
        $id);

      $this->setImportStatus($this->getImportStatus() | $flag);
    } else {
      queryfx(
        $conn_w,
        'UPDATE %T SET importStatus = (importStatus & ~%d) WHERE id = %d',
        $table_name,
        $flag,
        $id);

      $this->setImportStatus($this->getImportStatus() & ~$flag);
    }

    return $this;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'commitIdentifier' => 'text40',
        'authorPHID' => 'phid?',
        'authorIdentityPHID' => 'phid?',
        'committerIdentityPHID' => 'phid?',
        'auditStatus' => 'text32',
        'summary' => 'text255',
        'importStatus' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'repositoryID' => array(
          'columns' => array('repositoryID', 'importStatus'),
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID', 'auditStatus', 'epoch'),
        ),
        'repositoryID_2' => array(
          'columns' => array('repositoryID', 'epoch'),
        ),
        'key_commit_identity' => array(
          'columns' => array('commitIdentifier', 'repositoryID'),
          'unique' => true,
        ),
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
        'key_author' => array(
          'columns' => array('authorPHID', 'epoch'),
        ),
      ),
      self::CONFIG_NO_MUTATE => array(
        'importStatus',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryCommitPHIDType::TYPECONST);
  }

  public function loadCommitData() {
    if (!$this->getID()) {
      return null;
    }
    return id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $this->getID());
  }

  public function attachCommitData(
    PhabricatorRepositoryCommitData $data = null) {
    $this->commitData = $data;
    return $this;
  }

  public function hasCommitData() {
    return ($this->commitData !== self::ATTACHABLE) &&
           ($this->commitData !== null);
  }

  public function getCommitData() {
    return $this->assertAttached($this->commitData);
  }

  public function attachAudits(array $audits) {
    assert_instances_of($audits, 'PhabricatorRepositoryAuditRequest');
    $this->audits = $audits;
    return $this;
  }

  public function getAudits() {
    return $this->assertAttached($this->audits);
  }

  public function hasAttachedAudits() {
    return ($this->audits !== self::ATTACHABLE);
  }

  public function attachIdentities(
    PhabricatorRepositoryIdentity $author = null,
    PhabricatorRepositoryIdentity $committer = null) {

    $this->authorIdentity = $author;
    $this->committerIdentity = $committer;

    return $this;
  }

  public function getAuthorIdentity() {
    return $this->assertAttached($this->authorIdentity);
  }

  public function getCommitterIdentity() {
    return $this->assertAttached($this->committerIdentity);
  }

  public function attachAuditAuthority(
    PhabricatorUser $user,
    array $authority) {

    $user_phid = $user->getPHID();
    if (!$user->getPHID()) {
      throw new Exception(
        pht('You can not attach audit authority for a user with no PHID.'));
    }

    $this->auditAuthorityPHIDs[$user_phid] = $authority;

    return $this;
  }

  public function hasAuditAuthority(
    PhabricatorUser $user,
    PhabricatorRepositoryAuditRequest $audit) {

    $user_phid = $user->getPHID();
    if (!$user_phid) {
      return false;
    }

    $map = $this->assertAttachedKey($this->auditAuthorityPHIDs, $user_phid);

    return isset($map[$audit->getAuditorPHID()]);
  }

  public function writeOwnersEdges(array $package_phids) {
    $src_phid = $this->getPHID();
    $edge_type = DiffusionCommitHasPackageEdgeType::EDGECONST;

    $editor = new PhabricatorEdgeEditor();

    $dst_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $src_phid,
      $edge_type);

    foreach ($dst_phids as $dst_phid) {
      $editor->removeEdge($src_phid, $edge_type, $dst_phid);
    }

    foreach ($package_phids as $package_phid) {
      $editor->addEdge($src_phid, $edge_type, $package_phid);
    }

    $editor->save();

    return $this;
  }

  public function getAuditorPHIDsForEdit() {
    $audits = $this->getAudits();
    return mpull($audits, 'getAuditorPHID');
  }

  public function delete() {
    $data = $this->loadCommitData();
    $audits = id(new PhabricatorRepositoryAuditRequest())
      ->loadAllWhere('commitPHID = %s', $this->getPHID());
    $this->openTransaction();

      if ($data) {
        $data->delete();
      }
      foreach ($audits as $audit) {
        $audit->delete();
      }
      $result = parent::delete();

    $this->saveTransaction();
    return $result;
  }

  public function getDateCreated() {
    // This is primarily to make analysis of commits with the Fact engine work.
    return $this->getEpoch();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  /**
   * Synchronize a commit's overall audit status with the individual audit
   * triggers.
   */
  public function updateAuditStatus(array $requests) {
    assert_instances_of($requests, 'PhabricatorRepositoryAuditRequest');

    $any_concern = false;
    $any_accept = false;
    $any_need = false;

    foreach ($requests as $request) {
      switch ($request->getAuditStatus()) {
        case PhabricatorAuditRequestStatus::AUDIT_REQUIRED:
        case PhabricatorAuditRequestStatus::AUDIT_REQUESTED:
          $any_need = true;
          break;
        case PhabricatorAuditRequestStatus::ACCEPTED:
          $any_accept = true;
          break;
        case PhabricatorAuditRequestStatus::CONCERNED:
          $any_concern = true;
          break;
      }
    }

    if ($any_concern) {
      if ($this->isAuditStatusNeedsVerification()) {
        // If the change is in "Needs Verification", we keep it there as
        // long as any auditors still have concerns.
        $status = DiffusionCommitAuditStatus::NEEDS_VERIFICATION;
      } else {
        $status = DiffusionCommitAuditStatus::CONCERN_RAISED;
      }
    } else if ($any_accept) {
      if ($any_need) {
        $status = DiffusionCommitAuditStatus::PARTIALLY_AUDITED;
      } else {
        $status = DiffusionCommitAuditStatus::AUDITED;
      }
    } else if ($any_need) {
      $status = DiffusionCommitAuditStatus::NEEDS_AUDIT;
    } else {
      $status = DiffusionCommitAuditStatus::NONE;
    }

    return $this->setAuditStatus($status);
  }

  public function getMonogram() {
    $repository = $this->getRepository();
    $callsign = $repository->getCallsign();
    $identifier = $this->getCommitIdentifier();
    if ($callsign !== null) {
      return "r{$callsign}{$identifier}";
    } else {
      $id = $repository->getID();
      return "R{$id}:{$identifier}";
    }
  }

  public function getDisplayName() {
    $repository = $this->getRepository();
    $identifier = $this->getCommitIdentifier();
    return $repository->formatCommitName($identifier);
  }

  /**
   * Return a local display name for use in the context of the containing
   * repository.
   *
   * In Git and Mercurial, this returns only a short hash, like "abcdef012345".
   * See @{method:getDisplayName} for a short name that always includes
   * repository context.
   *
   * @return string Short human-readable name for use inside a repository.
   */
  public function getLocalName() {
    $repository = $this->getRepository();
    $identifier = $this->getCommitIdentifier();
    return $repository->formatCommitName($identifier, $local = true);
  }

  public function loadIdentities(PhabricatorUser $viewer) {
    if ($this->authorIdentity !== self::ATTACHABLE) {
      return $this;
    }

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->getID()))
      ->needIdentities(true)
      ->executeOne();

    $author_identity = $commit->getAuthorIdentity();
    $committer_identity = $commit->getCommitterIdentity();

    return $this->attachIdentities($author_identity, $committer_identity);
  }

  public function hasCommitterIdentity() {
    return ($this->getCommitterIdentity() !== null);
  }

  public function hasAuthorIdentity() {
    return ($this->getAuthorIdentity() !== null);
  }

  public function getCommitterDisplayPHID() {
    if ($this->hasCommitterIdentity()) {
      return $this->getCommitterIdentity()->getIdentityDisplayPHID();
    }

    $data = $this->getCommitData();
    return $data->getCommitDetail('committerPHID');
  }

  public function getAuthorDisplayPHID() {
    if ($this->hasAuthorIdentity()) {
      return $this->getAuthorIdentity()->getIdentityDisplayPHID();
    }

    $data = $this->getCommitData();
    return $data->getCommitDetail('authorPHID');
  }

  public function getEffectiveAuthorPHID() {
    if ($this->hasAuthorIdentity()) {
      $identity = $this->getAuthorIdentity();
      if ($identity->hasEffectiveUser()) {
        return $identity->getCurrentEffectiveUserPHID();
      }
    }

    $data = $this->getCommitData();
    return $data->getCommitDetail('authorPHID');
  }

  public function getAuditStatusObject() {
    $status = $this->getAuditStatus();
    return DiffusionCommitAuditStatus::newForStatus($status);
  }

  public function isAuditStatusNoAudit() {
    return $this->getAuditStatusObject()->isNoAudit();
  }

  public function isAuditStatusNeedsAudit() {
    return $this->getAuditStatusObject()->isNeedsAudit();
  }

  public function isAuditStatusConcernRaised() {
    return $this->getAuditStatusObject()->isConcernRaised();
  }

  public function isAuditStatusNeedsVerification() {
    return $this->getAuditStatusObject()->isNeedsVerification();
  }

  public function isAuditStatusPartiallyAudited() {
    return $this->getAuditStatusObject()->isPartiallyAudited();
  }

  public function isAuditStatusAudited() {
    return $this->getAuditStatusObject()->isAudited();
  }

  public function isPermanentCommit() {
    return (bool)$this->isPartiallyImported(self::IMPORTED_PERMANENT);
  }

  public function newCommitAuthorView(PhabricatorUser $viewer) {
    $author_phid = $this->getAuthorDisplayPHID();
    if ($author_phid) {
      $handles = $viewer->loadHandles(array($author_phid));
      return $handles[$author_phid]->renderLink();
    }

    $author = $this->getRawAuthorStringForDisplay();
    if ($author !== null && strlen($author)) {
      return DiffusionView::renderName($author);
    }

    return null;
  }

  public function newCommitCommitterView(PhabricatorUser $viewer) {
    $committer_phid = $this->getCommitterDisplayPHID();
    if ($committer_phid) {
      $handles = $viewer->loadHandles(array($committer_phid));
      return $handles[$committer_phid]->renderLink();
    }

    $committer = $this->getRawCommitterStringForDisplay();
    if ($committer !== null && strlen($committer)) {
      return DiffusionView::renderName($committer);
    }

    return null;
  }

  public function isAuthorSameAsCommitter() {
    $author_phid = $this->getAuthorDisplayPHID();
    $committer_phid = $this->getCommitterDisplayPHID();

    if ($author_phid && $committer_phid) {
      return ($author_phid === $committer_phid);
    }

    if ($author_phid || $committer_phid) {
      return false;
    }

    $author = $this->getRawAuthorStringForDisplay();
    $committer = $this->getRawCommitterStringForDisplay();

    return ($author === $committer);
  }

  private function getRawAuthorStringForDisplay() {
    $data = $this->getCommitData();
    return $data->getAuthorString();
  }

  private function getRawCommitterStringForDisplay() {
    $data = $this->getCommitData();
    return $data->getCommitterString();
  }

  public function getCommitMessageForDisplay() {
    $data = $this->getCommitData();
    $message = $data->getCommitMessage();
    return $message;
  }

  public function newCommitRef(PhabricatorUser $viewer) {
    $repository = $this->getRepository();

    $future = $repository->newConduitFuture(
      $viewer,
      'internal.commit.search',
      array(
        'constraints' => array(
          'repositoryPHIDs' => array($repository->getPHID()),
          'phids' => array($this->getPHID()),
        ),
      ));
    $result = $future->resolve();

    $commit_display = $this->getMonogram();

    if (empty($result['data'])) {
      throw new Exception(
        pht(
          'Unable to retrieve details for commit "%s"!',
          $commit_display));
    }

    if (count($result['data']) !== 1) {
      throw new Exception(
        pht(
          'Got too many results (%s) for commit "%s", expected %s.',
          phutil_count($result['data']),
          $commit_display,
          1));
    }

    $record = head($result['data']);
    $ref_record = idxv($record, array('fields', 'ref'));

    if (!$ref_record) {
      throw new Exception(
        pht(
          'Unable to retrieve CommitRef record for commit "%s".',
          $commit_display));
    }

    return DiffusionCommitRef::newFromDictionary($ref_record);
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getRepository()->getPolicy($capability);
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getRepository()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Commits inherit the policies of the repository they belong to.');
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */

  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }

/* -( Stuff for serialization )---------------------------------------------- */

  /**
   * NOTE: this is not a complete serialization; only the 'protected' fields are
   * involved. This is due to ease of (ab)using the Lisk abstraction to get this
   * done, as well as complexity of the other fields.
   */
  public function toDictionary() {
    return array(
      'repositoryID' => $this->getRepositoryID(),
      'phid' =>  $this->getPHID(),
      'commitIdentifier' =>  $this->getCommitIdentifier(),
      'epoch' => $this->getEpoch(),
      'authorPHID' => $this->getAuthorPHID(),
      'auditStatus' => $this->getAuditStatus(),
      'summary' => $this->getSummary(),
      'importStatus' => $this->getImportStatus(),
    );
  }

  public static function newFromDictionary(array $dict) {
    return id(new PhabricatorRepositoryCommit())
      ->loadFromArray($dict);
  }


/* -(  HarbormasterBuildableInterface  )------------------------------------- */


  public function getHarbormasterBuildableDisplayPHID() {
    return $this->getHarbormasterBuildablePHID();
  }

  public function getHarbormasterBuildablePHID() {
    return $this->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getRepository()->getPHID();
  }

  public function getBuildVariables() {
    $results = array();

    $results['buildable.commit'] = $this->getCommitIdentifier();
    $repo = $this->getRepository();

    $results['repository.callsign'] = $repo->getCallsign();
    $results['repository.phid'] = $repo->getPHID();
    $results['repository.vcs'] = $repo->getVersionControlSystem();
    $results['repository.uri'] = $repo->getPublicCloneURI();

    return $results;
  }

  public function getAvailableBuildVariables() {
    return array(
      'buildable.commit' => pht('The commit identifier, if applicable.'),
      'repository.callsign' =>
        pht('The callsign of the repository.'),
      'repository.phid' =>
        pht('The PHID of the repository.'),
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
    );
  }

  public function newBuildableEngine() {
    return new DiffusionBuildableEngine();
  }


/* -(  HarbormasterCircleCIBuildableInterface  )----------------------------- */


  public function getCircleCIGitHubRepositoryURI() {
    $repository = $this->getRepository();

    $commit_phid = $this->getPHID();
    $repository_phid = $repository->getPHID();

    if ($repository->isHosted()) {
      throw new Exception(
        pht(
          'This commit ("%s") is associated with a hosted repository '.
          '("%s"). Repositories must be imported from GitHub to be built '.
          'with CircleCI.',
          $commit_phid,
          $repository_phid));
    }

    $remote_uri = $repository->getRemoteURI();
    $path = HarbormasterCircleCIBuildStepImplementation::getGitHubPath(
      $remote_uri);
    if (!$path) {
      throw new Exception(
        pht(
          'This commit ("%s") is associated with a repository ("%s") which '.
          'has a remote URI ("%s") that does not appear to be hosted on '.
          'GitHub. Repositories must be hosted on GitHub to be built with '.
          'CircleCI.',
          $commit_phid,
          $repository_phid,
          $remote_uri));
    }

    return $remote_uri;
  }

  public function getCircleCIBuildIdentifierType() {
    return 'revision';
  }

  public function getCircleCIBuildIdentifier() {
    return $this->getCommitIdentifier();
  }


/* -(  HarbormasterBuildkiteBuildableInterface  )---------------------------- */


  public function getBuildkiteBranch() {
    $viewer = PhabricatorUser::getOmnipotentUser();
    $repository = $this->getRepository();

    $branches = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      DiffusionRequest::newFromDictionary(
        array(
          'repository' => $repository,
          'user' => $viewer,
        )),
      'diffusion.branchquery',
      array(
        'contains' => $this->getCommitIdentifier(),
        'repository' => $repository->getPHID(),
      ));

    if (!$branches) {
      throw new Exception(
        pht(
          'Commit "%s" is not an ancestor of any branch head, so it can not '.
          'be built with Buildkite.',
          $this->getCommitIdentifier()));
    }

    $branch = head($branches);

    return 'refs/heads/'.$branch['shortName'];
  }

  public function getBuildkiteCommit() {
    return $this->getCommitIdentifier();
  }


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('diffusion.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'PhabricatorCommitCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {

    // TODO: This should also list auditors, but handling that is a bit messy
    // right now because we are not guaranteed to have the data. (It should not
    // include resigned auditors.)

    return ($phid == $this->getAuthorPHID());
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuditEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuditTransaction();
  }

/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new DiffusionCommitFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new DiffusionCommitFerretEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */

  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('identifier')
        ->setType('string')
        ->setDescription(pht('The commit identifier.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('repositoryPHID')
        ->setType('phid')
        ->setDescription(pht('The repository this commit belongs to.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('author')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the commit author.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('committer')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the committer.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isImported')
        ->setType('bool')
        ->setDescription(pht('True if the commit is fully imported.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isUnreachable')
        ->setType('bool')
        ->setDescription(
          pht(
            'True if the commit is not the ancestor of any tag, branch, or '.
            'ref.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('auditStatus')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the current audit status.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('message')
        ->setType('string')
        ->setDescription(pht('The commit message.')),
    );
  }

  public function getFieldValuesForConduit() {
    $data = $this->getCommitData();

    $author_identity = $this->getAuthorIdentity();
    if ($author_identity) {
      $author_name = $author_identity->getIdentityDisplayName();
      $author_email = $author_identity->getIdentityEmailAddress();
      $author_raw = $author_identity->getIdentityName();
      $author_identity_phid = $author_identity->getPHID();
      $author_user_phid = $author_identity->getCurrentEffectiveUserPHID();
    } else {
      $author_name = null;
      $author_email = null;
      $author_raw = null;
      $author_identity_phid = null;
      $author_user_phid = null;
    }

    $committer_identity = $this->getCommitterIdentity();
    if ($committer_identity) {
      $committer_name = $committer_identity->getIdentityDisplayName();
      $committer_email = $committer_identity->getIdentityEmailAddress();
      $committer_raw = $committer_identity->getIdentityName();
      $committer_identity_phid = $committer_identity->getPHID();
      $committer_user_phid = $committer_identity->getCurrentEffectiveUserPHID();
    } else {
      $committer_name = null;
      $committer_email = null;
      $committer_raw = null;
      $committer_identity_phid = null;
      $committer_user_phid = null;
    }

    $author_epoch = $data->getAuthorEpoch();

    $audit_status = $this->getAuditStatusObject();

    return array(
      'identifier' => $this->getCommitIdentifier(),
      'repositoryPHID' => $this->getRepository()->getPHID(),
      'author' => array(
        'name' => $author_name,
        'email' => $author_email,
        'raw' => $author_raw,
        'epoch' => $author_epoch,
        'identityPHID' => $author_identity_phid,
        'userPHID' => $author_user_phid,
      ),
      'committer' => array(
        'name' => $committer_name,
        'email' => $committer_email,
        'raw' => $committer_raw,
        'epoch' => (int)$this->getEpoch(),
        'identityPHID' => $committer_identity_phid,
        'userPHID' => $committer_user_phid,
      ),
      'isUnreachable' => (bool)$this->isUnreachable(),
      'isImported' => (bool)$this->isImported(),
      'auditStatus' => array(
        'value' => $audit_status->getKey(),
        'name' => $audit_status->getName(),
        'closed' => (bool)$audit_status->getIsClosed(),
        'color.ansi' => $audit_status->getAnsiColor(),
      ),
      'message' => $data->getCommitMessage(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new DiffusionAuditorsSearchEngineAttachment())
        ->setAttachmentKey('auditors'),
    );
  }


/* -(  PhabricatorDraftInterface  )------------------------------------------ */

  public function newDraftEngine() {
    return new DiffusionCommitDraftEngine();
  }

  public function getHasDraft(PhabricatorUser $viewer) {
    return $this->assertAttachedKey($this->drafts, $viewer->getCacheFragment());
  }

  public function attachHasDraft(PhabricatorUser $viewer, $has_draft) {
    $this->drafts[$viewer->getCacheFragment()] = $has_draft;
    return $this;
  }


/* -(  PhabricatorTimelineInterface  )--------------------------------------- */


  public function newTimelineEngine() {
    return new DiffusionCommitTimelineEngine();
  }

}
