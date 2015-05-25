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
    PhabricatorCustomFieldInterface,
    PhabricatorApplicationTransactionInterface {

  protected $repositoryID;
  protected $phid;
  protected $commitIdentifier;
  protected $epoch;
  protected $mailKey;
  protected $authorPHID;
  protected $auditStatus = PhabricatorAuditCommitStatusConstants::NONE;
  protected $summary = '';
  protected $importStatus = 0;

  const IMPORTED_MESSAGE = 1;
  const IMPORTED_CHANGE = 2;
  const IMPORTED_OWNERS = 4;
  const IMPORTED_HERALD = 8;
  const IMPORTED_ALL = 15;

  const IMPORTED_CLOSEABLE = 1024;

  private $commitData = self::ATTACHABLE;
  private $audits = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;

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

  public function writeImportStatusFlag($flag) {
    queryfx(
      $this->establishConnection('w'),
      'UPDATE %T SET importStatus = (importStatus | %d) WHERE id = %d',
      $this->getTableName(),
      $flag,
      $this->getID());
    $this->setImportStatus($this->getImportStatus() | $flag);
    return $this;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'commitIdentifier' => 'text40',
        'mailKey' => 'bytes20',
        'authorPHID' => 'phid?',
        'auditStatus' => 'uint32',
        'summary' => 'text80',
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

  public function getAuthorityAudits(
    PhabricatorUser $user,
    array $authority_phids) {

    $authority = array_fill_keys($authority_phids, true);
    $audits = $this->getAudits();
    $authority_audits = array();
    foreach ($audits as $audit) {
      $has_authority = !empty($authority[$audit->getAuditorPHID()]);
      if ($has_authority) {
        $commit_author = $this->getAuthorPHID();

        // You don't have authority over package and project audits on your
        // own commits.

        $auditor_is_user = ($audit->getAuditorPHID() == $user->getPHID());
        $user_is_author = ($commit_author == $user->getPHID());

        if ($auditor_is_user || !$user_is_author) {
          $authority_audits[$audit->getID()] = $audit;
        }
      }
    }
    return $authority_audits;
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }
    return parent::save();
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
    $repository = $this->getRepository();
    $callsign = $repository->getCallsign();
    $commit_identifier = $this->getCommitIdentifier();
    return '/r'.$callsign.$commit_identifier;
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
        case PhabricatorAuditStatusConstants::AUDIT_REQUIRED:
          $any_need = true;
          break;
        case PhabricatorAuditStatusConstants::ACCEPTED:
          $any_accept = true;
          break;
        case PhabricatorAuditStatusConstants::CONCERNED:
          $any_concern = true;
          break;
      }
    }

    if ($any_concern) {
      $status = PhabricatorAuditCommitStatusConstants::CONCERN_RAISED;
    } else if ($any_accept) {
      if ($any_need) {
        $status = PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED;
      } else {
        $status = PhabricatorAuditCommitStatusConstants::FULLY_AUDITED;
      }
    } else if ($any_need) {
      $status = PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT;
    } else {
      $status = PhabricatorAuditCommitStatusConstants::NONE;
    }

    return $this->setAuditStatus($status);
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
      'mailKey' => $this->getMailKey(),
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
    $results['repository.vcs'] = $repo->getVersionControlSystem();
    $results['repository.uri'] = $repo->getPublicCloneURI();

    return $results;
  }

  public function getAvailableBuildVariables() {
    return array(
      'buildable.commit' => pht('The commit identifier, if applicable.'),
      'repository.callsign' =>
        pht('The callsign of the repository in Phabricator.'),
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
    );
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
    // right now because we are not guaranteed to have the data.

    return ($phid == $this->getAuthorPHID());
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuditEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuditTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    $xactions = $timeline->getTransactions();

    $path_ids = array();
    foreach ($xactions as $xaction) {
      if ($xaction->hasComment()) {
        $path_id = $xaction->getComment()->getPathID();
        if ($path_id) {
          $path_ids[] = $path_id;
        }
      }
    }

    $path_map = array();
    if ($path_ids) {
      $path_map = id(new DiffusionPathQuery())
        ->withPathIDs($path_ids)
        ->execute();
      $path_map = ipull($path_map, 'path', 'id');
    }

    return $timeline->setPathMap($path_map);
  }

}
