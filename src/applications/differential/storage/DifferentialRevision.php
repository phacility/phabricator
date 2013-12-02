<?php

final class DifferentialRevision extends DifferentialDAO
  implements
    PhabricatorTokenReceiverInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhrequentTrackableInterface {

  protected $title = '';
  protected $originalTitle;
  protected $status;

  protected $summary = '';
  protected $testPlan = '';

  protected $authorPHID;
  protected $lastReviewerPHID;

  protected $dateCommitted;

  protected $lineCount = 0;
  protected $attached = array();

  protected $mailKey;
  protected $branchName;
  protected $arcanistProjectPHID;
  protected $repositoryPHID;
  protected $viewPolicy = PhabricatorPolicies::POLICY_USER;
  protected $editPolicy = PhabricatorPolicies::POLICY_USER;

  private $relationships = self::ATTACHABLE;
  private $commits = self::ATTACHABLE;
  private $activeDiff = self::ATTACHABLE;
  private $diffIDs = self::ATTACHABLE;
  private $hashes = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  private $reviewerStatus = self::ATTACHABLE;

  const RELATIONSHIP_TABLE    = 'differential_relationship';
  const TABLE_COMMIT          = 'differential_commit';

  const RELATION_REVIEWER     = 'revw';
  const RELATION_SUBSCRIBED   = 'subd';

  public static function initializeNewRevision(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorApplicationDifferential'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      DifferentialCapabilityDefaultView::CAPABILITY);

    return id(new DifferentialRevision())
      ->setViewPolicy($view_policy)
      ->setAuthorPHID($actor->getPHID())
      ->attachRelationships(array())
      ->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
  }

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
    return $this->assertAttached($this->commits);
  }

  public function getActiveDiff() {
    // TODO: Because it's currently technically possible to create a revision
    // without an associated diff, we allow an attached-but-null active diff.
    // It would be good to get rid of this once we make diff-attaching
    // transactional.

    return $this->assertAttached($this->activeDiff);
  }

  public function attachActiveDiff($diff) {
    $this->activeDiff = $diff;
    return $this;
  }

  public function getDiffIDs() {
    return $this->assertAttached($this->diffIDs);
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
      DifferentialPHIDTypeRevision::TYPECONST);
  }

  public function loadComments() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialCommentQuery())
      ->withRevisionIDs(array($this->getID()))
      ->execute();
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
    $diffs = id(new DifferentialDiffQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRevisionIDs(array($this->getID()))
      ->execute();
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

      $comments = id(new DifferentialCommentQuery())
        ->withRevisionIDs(array($this->getID()))
        ->execute();
      foreach ($comments as $comment) {
        $comment->delete();
      }

      $inlines = id(new DifferentialInlineCommentQuery())
        ->withRevisionIDs(array($this->getID()))
        ->execute();
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

    // Read "subscribed" and "unsubscribed" data out of the old relationship
    // table.
    $data = queryfx_all(
      $this->establishConnection('r'),
      'SELECT * FROM %T WHERE revisionID = %d
        AND relation != %s ORDER BY sequence',
      self::RELATIONSHIP_TABLE,
      $this->getID(),
      self::RELATION_REVIEWER);

    // Read "reviewer" data out of the new table.
    $reviewer_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER);
    $reviewer_phids = array_reverse($reviewer_phids);

    foreach ($reviewer_phids as $phid) {
      $data[] = array(
        'relation' => self::RELATION_REVIEWER,
        'objectPHID' => $phid,
        'reasonPHID' => null,
      );
    }

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
    $this->assertAttached($this->relationships);

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
    return $this->assertAttached($this->hashes);
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
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {

    // A revision's author (which effectively means "owner" after we added
    // commandeering) can always view and edit it.
    $author_phid = $this->getAuthorPHID();
    if ($author_phid) {
      if ($user->getPHID() == $author_phid) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    $description = array(
      pht('The owner of a revision can always view and edit it.'),
    );

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $description[] = pht(
          "A revision's reviewers can always view it.");
        $description[] = pht(
          'If a revision belongs to a repository, other users must be able '.
          'to view the repository in order to view the revision.');
        break;
    }

    return $description;
  }

  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }

  public function getReviewerStatus() {
    return $this->assertAttached($this->reviewerStatus);
  }

  public function attachReviewerStatus(array $reviewers) {
    assert_instances_of($reviewers, 'DifferentialReviewer');

    $this->reviewerStatus = $reviewers;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachRepository(PhabricatorRepository $repository = null) {
    $this->repository = $repository;
    return $this;
  }

  public function isClosed() {
    return DifferentialRevisionStatus::isClosedStatus($this->getStatus());
  }

}
