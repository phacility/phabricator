<?php

final class ReleephRequest extends ReleephDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorCustomFieldInterface {

  protected $branchID;
  protected $requestUserPHID;
  protected $details = array();
  protected $userIntents = array();
  protected $inBranch;
  protected $pickStatus;
  protected $mailKey;

  /**
   * The object which is being requested. Normally this is a commit, but it
   * might also be a revision. In the future, it could be a repository branch
   * or an external object (like a GitHub pull request).
   */
  protected $requestedObjectPHID;

  // Information about the thing being requested
  protected $requestCommitPHID;

  // Information about the last commit to the releeph branch
  protected $commitIdentifier;
  protected $commitPHID;


  private $customFields = self::ATTACHABLE;
  private $branch = self::ATTACHABLE;
  private $requestedObject = self::ATTACHABLE;


/* -(  Constants and helper methods  )--------------------------------------- */

  const INTENT_WANT = 'want';
  const INTENT_PASS = 'pass';

  const PICK_PENDING  = 1; // old
  const PICK_FAILED   = 2;
  const PICK_OK       = 3;
  const PICK_MANUAL   = 4; // old
  const REVERT_OK     = 5;
  const REVERT_FAILED = 6;

  public function shouldBeInBranch() {
    return
      $this->getPusherIntent() == self::INTENT_WANT &&
      /**
       * We use "!= pass" instead of "== want" in case the requestor intent is
       * not present. In other words, only revert if the requestor explicitly
       * passed.
       */
      $this->getRequestorIntent() != self::INTENT_PASS;
  }

  /**
   * Will return INTENT_WANT if any pusher wants this request, and no pusher
   * passes on this request.
   */
  public function getPusherIntent() {
    $product = $this->getBranch()->getProduct();

    if (!$product->getPushers()) {
      return self::INTENT_WANT;
    }

    $found_pusher_want = false;
    foreach ($this->userIntents as $phid => $intent) {
      if ($product->isAuthoritativePHID($phid)) {
        if ($intent == self::INTENT_PASS) {
          return self::INTENT_PASS;
        }

        $found_pusher_want = true;
      }
    }

    if ($found_pusher_want) {
      return self::INTENT_WANT;
    } else {
      return null;
    }
  }

  public function getRequestorIntent() {
    return idx($this->userIntents, $this->requestUserPHID);
  }

  public function getStatus() {
    return $this->calculateStatus();
  }

  public function getMonogram() {
    return 'Y'.$this->getID();
  }

  public function getBranch() {
    return $this->assertAttached($this->branch);
  }

  public function attachBranch(ReleephBranch $branch) {
    $this->branch = $branch;
    return $this;
  }

  public function getRequestedObject() {
    return $this->assertAttached($this->requestedObject);
  }

  public function attachRequestedObject($object) {
    $this->requestedObject = $object;
    return $this;
  }

  private function calculateStatus() {
    if ($this->shouldBeInBranch()) {
      if ($this->getInBranch()) {
        return ReleephRequestStatus::STATUS_PICKED;
      } else {
        return ReleephRequestStatus::STATUS_NEEDS_PICK;
      }
    } else {
      if ($this->getInBranch()) {
        return ReleephRequestStatus::STATUS_NEEDS_REVERT;
      } else {
        $intent_pass = self::INTENT_PASS;
        $intent_want = self::INTENT_WANT;

        $has_been_in_branch = $this->getCommitIdentifier();
        // Regardless of why we reverted something, always say reverted if it
        // was once in the branch.
        if ($has_been_in_branch) {
          return ReleephRequestStatus::STATUS_REVERTED;
        } else if ($this->getPusherIntent() === $intent_pass) {
          // Otherwise, if it has never been in the branch, explicitly say why:
          return ReleephRequestStatus::STATUS_REJECTED;
        } else if ($this->getRequestorIntent() === $intent_want) {
          return ReleephRequestStatus::STATUS_REQUESTED;
        } else {
          return ReleephRequestStatus::STATUS_ABANDONED;
        }
      }
    }
  }


/* -(  Lisk mechanics  )----------------------------------------------------- */

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
        'userIntents' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'requestCommitPHID' => 'phid?',
        'commitIdentifier' => 'text40?',
        'commitPHID' => 'phid?',
        'pickStatus' => 'uint32?',
        'inBranch' => 'bool',
        'mailKey' => 'bytes20',
        'userIntents' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'requestIdentifierBranch' => array(
          'columns' => array('requestCommitPHID', 'branchID'),
          'unique' => true,
        ),
        'branchID' => array(
          'columns' => array('branchID'),
        ),
        'key_requestedObject' => array(
          'columns' => array('requestedObjectPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      ReleephRequestPHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }


/* -(  Helpful accessors )--------------------------------------------------- */


  public function getDetail($key, $default = null) {
    return idx($this->getDetails(), $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }


  /**
   * Get the commit PHIDs this request is requesting.
   *
   * NOTE: For now, this always returns one PHID.
   *
   * @return list<phid> Commit PHIDs requested by this request.
   */
  public function getCommitPHIDs() {
    return array(
      $this->requestCommitPHID,
    );
  }

  public function getReason() {
    // Backward compatibility: reason used to be called comments
    $reason = $this->getDetail('reason');
    if (!$reason) {
      return $this->getDetail('comments');
    }
    return $reason;
  }

  /**
   * Allow a null summary, and fall back to the title of the commit.
   */
  public function getSummaryForDisplay() {
    $summary = $this->getDetail('summary');

    if (!strlen($summary)) {
      $commit = $this->loadPhabricatorRepositoryCommit();
      if ($commit) {
        $summary = $commit->getSummary();
      }
    }

    if (!strlen($summary)) {
      $summary = pht('None');
    }

    return $summary;
  }

/* -(  Loading external objects  )------------------------------------------- */

  public function loadPhabricatorRepositoryCommit() {
    return $this->loadOneRelative(
      new PhabricatorRepositoryCommit(),
      'phid',
      'getRequestCommitPHID');
  }

  public function loadPhabricatorRepositoryCommitData() {
    $commit = $this->loadPhabricatorRepositoryCommit();
    if ($commit) {
      return $commit->loadOneRelative(
        new PhabricatorRepositoryCommitData(),
        'commitID');
    }
  }


/* -(  State change helpers  )----------------------------------------------- */

  public function setUserIntent(PhabricatorUser $user, $intent) {
    $this->userIntents[$user->getPHID()] = $intent;
    return $this;
  }


/* -(  Migrating to status-less ReleephRequests  )--------------------------- */

  protected function didReadData() {
    if ($this->userIntents === null) {
      $this->userIntents = array();
    }
  }

  public function setStatus($value) {
    throw new Exception(pht('`%s` is now deprecated!', 'status'));
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new ReleephRequestTransactionalEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new ReleephRequestTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getBranch()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBranch()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Pull requests have the same policies as the branches they are '.
      'requested against.');
  }



/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('releeph.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'ReleephFieldSpecification';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


}
