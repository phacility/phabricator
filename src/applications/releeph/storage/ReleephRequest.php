<?php

final class ReleephRequest extends ReleephDAO {

  protected $phid;
  protected $branchID;
  protected $requestUserPHID;
  protected $details = array();
  protected $userIntents = array();
  protected $inBranch;
  protected $pickStatus;

  // Information about the thing being requested
  protected $requestCommitIdentifier;
  protected $requestCommitPHID;
  protected $requestCommitOrdinal;

  // Information about the last commit to the releeph branch
  protected $commitIdentifier;
  protected $committedByUserPHID;
  protected $commitPHID;

  // Pre-populated handles that we'll bulk load in ReleephBranch
  private $handles;


/* -(  Constants and helper methods  )--------------------------------------- */

  const INTENT_WANT = 'want';
  const INTENT_PASS = 'pass';

  const PICK_PENDING  = 1; // old
  const PICK_FAILED   = 2;
  const PICK_OK       = 3;
  const PICK_MANUAL   = 4; // old
  const REVERT_OK     = 5;
  const REVERT_FAILED = 6;

  const STATUS_REQUESTED       = 1;
  const STATUS_NEEDS_PICK      = 2;  // aka approved
  const STATUS_REJECTED        = 3;
  const STATUS_ABANDONED       = 4;
  const STATUS_PICKED          = 5;
  const STATUS_REVERTED        = 6;
  const STATUS_NEEDS_REVERT    = 7;  // aka revert requested

  public function shouldBeInBranch() {
    return
      $this->getPusherIntent() == self::INTENT_WANT &&
      /**
       * We use "!= pass" instead of "== want" in case the requestor intent is
       * not present.  In other words, only revert if the requestor explicitly
       * passed.
       */
      $this->getRequestorIntent() != self::INTENT_PASS;
  }

  /**
   * Will return INTENT_WANT if any pusher wants this request, and no pusher
   * passes on this request.
   */
  public function getPusherIntent() {
    $project = $this->loadReleephProject();
    if (!$project->getPushers()) {
      return self::INTENT_WANT;
    }

    $found_pusher_want = false;
    foreach ($this->userIntents as $phid => $intent) {
      if ($project->isPusherPHID($phid)) {
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

  private function calculateStatus() {
    if ($this->shouldBeInBranch()) {
      if ($this->getInBranch()) {
        return self::STATUS_PICKED;
      } else {
        return self::STATUS_NEEDS_PICK;
      }
    } else {
      if ($this->getInBranch()) {
        return self::STATUS_NEEDS_REVERT;
      } else {
        $has_been_in_branch = $this->getCommitIdentifier();
        // Regardless of why we reverted something, always say reverted if it
        // was once in the branch.
        if ($has_been_in_branch) {
          return self::STATUS_REVERTED;
        } elseif ($this->getPusherIntent() === ReleephRequest::INTENT_PASS) {
          // Otherwise, if it has never been in the branch, explicitly say why:
          return self::STATUS_REJECTED;
        } elseif ($this->getRequestorIntent() === ReleephRequest::INTENT_WANT) {
          return self::STATUS_REQUESTED;
        } else {
          return self::STATUS_ABANDONED;
        }
      }
    }
  }

  public static function getStatusDescriptionFor($status) {
    static $descriptions = array(
      self::STATUS_REQUESTED       => 'Requested',
      self::STATUS_REJECTED        => 'Rejected',
      self::STATUS_ABANDONED       => 'Abandoned',
      self::STATUS_PICKED          => 'Picked',
      self::STATUS_REVERTED        => 'Reverted',
      self::STATUS_NEEDS_PICK      => 'Needs Pick',
      self::STATUS_NEEDS_REVERT    => 'Needs Revert',
    );
    return idx($descriptions, $status, '??');
  }

  public static function getStatusClassSuffixFor($status) {
    $description = self::getStatusDescriptionFor($status);
    $class = str_replace(' ', '-', strtolower($description));
    return $class;
  }


/* -(  Lisk mechanics  )----------------------------------------------------- */

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
        'userIntents' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      ReleephPHIDConstants::PHID_TYPE_RERQ);
  }


/* -(  Helpful accessors )--------------------------------------------------- */

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    if (!$this->handles) {
      throw new Exception(
        "You must call ReleephBranch::populateReleephRequestHandles() first");
    }
    return $this->handles;
  }

  public function getDetail($key, $default = null) {
    return idx($this->getDetails(), $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function getReason() {
    // Backward compatibility: reason used to be called comments
    $reason = $this->getDetail('reason');
    if (!$reason) {
      return $this->getDetail('comments');
    }
    return $reason;
  }

  public function getSummary() {
    /**
     * Instead, you can use:
     *  - getDetail('summary')    // the actual user-chosen summary
     *  - getSummaryForDisplay()  // falls back to the original commit title
     *
     * Or for the fastidious:
     *  - id(new ReleephSummaryFieldSpecification())
     *      ->setReleephRequest($rr)
     *      ->getValue()          // programmatic equivalent to getDetail()
     */
    throw new Exception(
      "getSummary() has been deprecated!");
  }

  /**
   * Allow a null summary, and fall back to the title of the commit.
   */
  public function getSummaryForDisplay() {
    $summary = $this->getDetail('summary');

    if (!$summary) {
      $pr_commit_data = $this->loadPhabricatorRepositoryCommitData();
      if ($pr_commit_data) {
        $message_lines = explode("\n", $pr_commit_data->getCommitMessage());
        $message_lines = array_filter($message_lines);
        $summary = head($message_lines);
      }
    }

    if (!$summary) {
      $summary = '(no summary given and commit message empty or unparsed)';
    }

    return $summary;
  }

  public function loadRequestCommitDiffPHID() {
    $commit_data = $this->loadPhabricatorRepositoryCommitData();
    if (!$commit_data) {
      return null;
    }
    return $commit_data->getCommitDetail('differential.revisionPHID');
  }


/* -(  Loading external objects  )------------------------------------------- */

  public function loadReleephBranch() {
    return $this->loadOneRelative(
      new ReleephBranch(),
      'id',
      'getBranchID');
  }

  public function loadReleephProject() {
    return $this->loadReleephBranch()->loadReleephProject();
  }

  public function loadEvents() {
    return $this->loadRelatives(
      new ReleephRequestEvent(),
      'releephRequestID',
      'getID',
      '(1 = 1) ORDER BY dateCreated, id');
  }

  public function loadPhabricatorRepositoryCommit() {
    return $this->loadOneRelative(
      new PhabricatorRepositoryCommit(),
      'phid',
      'getRequestCommitPHID');
  }

  public function loadPhabricatorRepositoryCommitData() {
    return $this->loadOneRelative(
      new PhabricatorRepositoryCommitData(),
      'commitID',
      'getRequestCommitOrdinal');
  }

  public function loadDifferentialRevision() {
    return $this->loadOneRelative(
        new DifferentialRevision(),
        'phid',
        'loadRequestCommitDiffPHID');
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
    throw new Exception('`status` is now deprecated!');
  }


/* -(  Make magic Lisk methods private  )------------------------------------ */

  private function setUserIntents(array $ar) {
    return parent::setUserIntents($ar);
  }

}
