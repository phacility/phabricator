<?php

final class DifferentialRevision extends DifferentialDAO
  implements
    PhabricatorTokenReceiverInterface,
    PhabricatorPolicyInterface,
    PhrequentTrackableInterface {

  protected $title;
  protected $originalTitle;
  protected $status;

  protected $summary;
  protected $testPlan;

  protected $phid;
  protected $authorPHID;
  protected $lastReviewerPHID;

  protected $dateCommitted;

  protected $lineCount;
  protected $attached = array();

  protected $mailKey;
  protected $branchName;
  protected $arcanistProjectPHID;

  private $relationships;
  private $commits;
  private $activeDiff = false;
  private $diffIDs;
  private $hashes;


  const RELATIONSHIP_TABLE    = 'differential_relationship';
  const TABLE_COMMIT          = 'differential_commit';

  const RELATION_REVIEWER     = 'revw';
  const RELATION_SUBSCRIBED   = 'subd';

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attached'      => self::SERIALIZATION_JSON,
        'unsubscribed'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function setTitle($title) {
    $this->title = $title;
    if (!$this->getID()) {
      $this->originalTitle = $title;
    }
    return $this;
  }

  public function loadIDsByCommitPHIDs($phids) {
    if (!$phids) {
      return array();
    }
    $revision_ids = queryfx_all(
      $this->establishConnection('r'),
      'SELECT * FROM %T WHERE commitPHID IN (%Ls)',
      self::TABLE_COMMIT,
      $phids);
    return ipull($revision_ids, 'revisionID', 'commitPHID');
  }

  public function loadCommitPHIDs() {
    if (!$this->getID()) {
      return ($this->commits = array());
    }

    $commits = queryfx_all(
      $this->establishConnection('r'),
      'SELECT commitPHID FROM %T WHERE revisionID = %d',
      self::TABLE_COMMIT,
      $this->getID());
    $commits = ipull($commits, 'commitPHID');

    return ($this->commits = $commits);
  }

  public function getCommitPHIDs() {
    if ($this->commits === null) {
      throw new Exception("Must attach commits first!");
    }
    return $this->commits;
  }

  public function getActiveDiff() {
    // TODO: Because it's currently technically possible to create a revision
    // without an associated diff, we allow an attached-but-null active diff.
    // It would be good to get rid of this once we make diff-attaching
    // transactional.

    if ($this->activeDiff === false) {
      throw new Exception("Must attach active diff first!");
    }
    return $this->activeDiff;
  }

  public function attachActiveDiff($diff) {
    $this->activeDiff = $diff;
    return $this;
  }

  public function getDiffIDs() {
    if ($this->diffIDs === null) {
      throw new Exception("Must attach diff IDs first!");
    }
    return $this->diffIDs;
  }

  public function attachDiffIDs(array $ids) {
    rsort($ids);
    $this->diffIDs = array_values($ids);
    return $this;
  }

  public function attachCommitPHIDs(array $phids) {
    $this->commits = array_values($phids);
    return $this;
  }

  public function getAttachedPHIDs($type) {
    return array_keys(idx($this->attached, $type, array()));
  }

  public function setAttachedPHIDs($type, array $phids) {
    $this->attached[$type] = array_fill_keys($phids, array());
    return $this;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_DREV);
  }

  public function loadDiffs() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialDiff())->loadAllWhere(
      'revisionID = %d',
      $this->getID());
  }

  public function loadComments() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialComment())->loadAllWhere(
      'revisionID = %d',
      $this->getID());
  }

  public function loadActiveDiff() {
    return id(new DifferentialDiff())->loadOneWhere(
      'revisionID = %d ORDER BY id DESC LIMIT 1',
      $this->getID());
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->mailKey = Filesystem::readRandomCharacters(40);
    }
    return parent::save();
  }

  public function delete() {
    $this->openTransaction();
      $diffs = $this->loadDiffs();
      foreach ($diffs as $diff) {
        $diff->delete();
      }

      $conn_w = $this->establishConnection('w');

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d',
        self::RELATIONSHIP_TABLE,
        $this->getID());

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d',
        self::TABLE_COMMIT,
        $this->getID());

      $comments = id(new DifferentialComment())->loadAllWhere(
        'revisionID = %d',
        $this->getID());
      foreach ($comments as $comment) {
        $comment->delete();
      }

      $inlines = id(new DifferentialInlineComment())->loadAllWhere(
        'revisionID = %d',
        $this->getID());
      foreach ($inlines as $inline) {
        $inline->delete();
      }

      $fields = id(new DifferentialAuxiliaryField())->loadAllWhere(
        'revisionPHID = %s',
        $this->getPHID());
      foreach ($fields as $field) {
        $field->delete();
      }

      // we have to do paths a little differentally as they do not have
      // an id or phid column for delete() to act on
      $dummy_path = new DifferentialAffectedPath();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d',
        $dummy_path->getTableName(),
        $this->getID());

      $result = parent::delete();
    $this->saveTransaction();
    return $result;
  }

  public function loadRelationships() {
    if (!$this->getID()) {
      $this->relationships = array();
      return;
    }

    $data = queryfx_all(
      $this->establishConnection('r'),
      'SELECT * FROM %T WHERE revisionID = %d ORDER BY sequence',
      self::RELATIONSHIP_TABLE,
      $this->getID());
    return $this->attachRelationships($data);
  }

  public function attachRelationships(array $relationships) {
    $this->relationships = igroup($relationships, 'relation');
    return $this;
  }

  public function getReviewers() {
    return $this->getRelatedPHIDs(self::RELATION_REVIEWER);
  }

  public function getCCPHIDs() {
    return $this->getRelatedPHIDs(self::RELATION_SUBSCRIBED);
  }

  private function getRelatedPHIDs($relation) {
    if ($this->relationships === null) {
      throw new Exception("Must load relationships!");
    }

    return ipull($this->getRawRelations($relation), 'objectPHID');
  }

  public function getRawRelations($relation) {
    return idx($this->relationships, $relation, array());
  }

  public function loadUnsubscribedPHIDs() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->phid,
      PhabricatorEdgeConfig::TYPE_OBJECT_HAS_UNSUBSCRIBER);
  }

  public function getPrimaryReviewer() {
    $reviewers = $this->getReviewers();
    $last = $this->lastReviewerPHID;
    if (!$last || !in_array($last, $reviewers)) {
      return head($this->getReviewers());
    }
    return $last;
  }

  public function loadReviewedBy() {
    $reviewer = null;

    if ($this->status == ArcanistDifferentialRevisionStatus::ACCEPTED ||
        $this->status == ArcanistDifferentialRevisionStatus::CLOSED) {
      $comments = $this->loadComments();
      foreach ($comments as $comment) {
        $action = $comment->getAction();
        if ($action == DifferentialAction::ACTION_ACCEPT) {
          $reviewer = $comment->getAuthorPHID();
        } else if ($action == DifferentialAction::ACTION_REJECT ||
                   $action == DifferentialAction::ACTION_ABANDON ||
                   $action == DifferentialAction::ACTION_RETHINK) {
          $reviewer = null;
        }
      }
    }

    return $reviewer;
  }

  public function getHashes() {
    if ($this->hashes === null) {
      throw new Exception("Call attachHashes() before getHashes()!");
    }
    return $this->hashes;
  }

  public function attachHashes(array $hashes) {
    $this->hashes = $hashes;
    return $this;
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return false;
  }

  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }

}
