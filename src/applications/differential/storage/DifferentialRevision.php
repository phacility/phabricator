<?php

final class DifferentialRevision extends DifferentialDAO
  implements
    PhabricatorTokenReceiverInterface,
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorFlaggableInterface,
    PhrequentTrackableInterface,
    HarbormasterBuildableInterface,
    PhabricatorSubscribableInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorMentionableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface {

  protected $title = '';
  protected $originalTitle;
  protected $status;

  protected $summary = '';
  protected $testPlan = '';

  protected $authorPHID;
  protected $lastReviewerPHID;

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
  private $customFields = self::ATTACHABLE;
  private $drafts = array();
  private $flags = array();

  const TABLE_COMMIT          = 'differential_commit';

  const RELATION_REVIEWER     = 'revw';
  const RELATION_SUBSCRIBED   = 'subd';

  public static function initializeNewRevision(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorDifferentialApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      DifferentialDefaultViewCapability::CAPABILITY);

    return id(new DifferentialRevision())
      ->setViewPolicy($view_policy)
      ->setAuthorPHID($actor->getPHID())
      ->attachRelationships(array())
      ->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attached'      => self::SERIALIZATION_JSON,
        'unsubscribed'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'originalTitle' => 'text255',
        'status' => 'text32',
        'summary' => 'text',
        'testPlan' => 'text',
        'authorPHID' => 'phid?',
        'lastReviewerPHID' => 'phid?',
        'lineCount' => 'uint32?',
        'mailKey' => 'bytes40',
        'branchName' => 'text255?',
        'arcanistProjectPHID' => 'phid?',
        'repositoryPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID', 'status'),
        ),
        'repositoryPHID' => array(
          'columns' => array('repositoryPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getMonogram() {
    $id = $this->getID();
    return "D{$id}";
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
      DifferentialRevisionPHIDType::TYPECONST);
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

  public function loadRelationships() {
    if (!$this->getID()) {
      $this->relationships = array();
      return;
    }

    $data = array();

    $subscriber_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      PhabricatorObjectHasSubscriberEdgeType::EDGECONST);
    $subscriber_phids = array_reverse($subscriber_phids);
    foreach ($subscriber_phids as $phid) {
      $data[] = array(
        'relation' => self::RELATION_SUBSCRIBED,
        'objectPHID' => $phid,
        'reasonPHID' => null,
      );
    }

    $reviewer_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      DifferentialRevisionHasReviewerEdgeType::EDGECONST);
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

  public function getPrimaryReviewer() {
    $reviewers = $this->getReviewers();
    $last = $this->lastReviewerPHID;
    if (!$last || !in_array($last, $reviewers)) {
      return head($this->getReviewers());
    }
    return $last;
  }

  public function getHashes() {
    return $this->assertAttached($this->hashes);
  }

  public function attachHashes(array $hashes) {
    $this->hashes = $hashes;
    return $this;
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
        $description[] = pht("A revision's reviewers can always view it.");
        $description[] = pht(
          'If a revision belongs to a repository, other users must be able '.
          'to view the repository in order to view the revision.');
        break;
    }

    return $description;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // NOTE: In Differential, an automatic capability on a revision (being
        // an author) is sufficient to view it, even if you can not see the
        // repository the revision belongs to. We can bail out early in this
        // case.
        if ($this->hasAutomaticCapability($capability, $viewer)) {
          break;
        }

        $repository_phid = $this->getRepositoryPHID();
        $repository = $this->getRepository();

        // Try to use the object if we have it, since it will save us some
        // data fetching later on. In some cases, we might not have it.
        $repository_ref = nonempty($repository, $repository_phid);
        if ($repository_ref) {
          $extended[] = array(
            $repository_ref,
            PhabricatorPolicyCapability::CAN_VIEW,
          );
        }
        break;
    }

    return $extended;
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


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

  public function getFlag(PhabricatorUser $viewer) {
    return $this->assertAttachedKey($this->flags, $viewer->getPHID());
  }

  public function attachFlag(
    PhabricatorUser $viewer,
    PhabricatorFlag $flag = null) {
    $this->flags[$viewer->getPHID()] = $flag;
    return $this;
  }

  public function getDrafts(PhabricatorUser $viewer) {
    return $this->assertAttachedKey($this->drafts, $viewer->getPHID());
  }

  public function attachDrafts(PhabricatorUser $viewer, array $drafts) {
    $this->drafts[$viewer->getPHID()] = $drafts;
    return $this;
  }


/* -(  HarbormasterBuildableInterface  )------------------------------------- */


  public function getHarbormasterBuildablePHID() {
    return $this->loadActiveDiff()->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getPHID();
  }

  public function getBuildVariables() {
    return array();
  }

  public function getAvailableBuildVariables() {
    return array();
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    if ($phid == $this->getAuthorPHID()) {
      return true;
    }

    // TODO: This only happens when adding or removing CCs, and is safe from a
    // policy perspective, but the subscription pathway should have some
    // opportunity to load this data properly. For now, this is the only case
    // where implicit subscription is not an intrinsic property of the object.
    if ($this->reviewerStatus == self::ATTACHABLE) {
      $reviewers = id(new DifferentialRevisionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs(array($this->getPHID()))
        ->needReviewerStatus(true)
        ->executeOne()
        ->getReviewerStatus();
    } else {
      $reviewers = $this->getReviewerStatus();
    }

    foreach ($reviewers as $reviewer) {
      if ($reviewer->getReviewerPHID() == $phid) {
        return true;
      }
    }

    return false;
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('differential.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'DifferentialCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DifferentialTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new DifferentialTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    $viewer = $request->getViewer();

    $render_data = $timeline->getRenderData();
    $left = $request->getInt('left', idx($render_data, 'left'));
    $right = $request->getInt('right', idx($render_data, 'right'));

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($left, $right))
      ->execute();
    $diffs = mpull($diffs, null, 'getID');
    $left_diff = $diffs[$left];
    $right_diff = $diffs[$right];

    $old_ids = $request->getStr('old', idx($render_data, 'old'));
    $new_ids = $request->getStr('new', idx($render_data, 'new'));
    $old_ids = array_filter(explode(',', $old_ids));
    $new_ids = array_filter(explode(',', $new_ids));

    $type_inline = DifferentialTransaction::TYPE_INLINE;
    $changeset_ids = array_merge($old_ids, $new_ids);
    $inlines = array();
    foreach ($timeline->getTransactions() as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction->getComment();
        $changeset_ids[] = $xaction->getComment()->getChangesetID();
      }
    }

    if ($changeset_ids) {
      $changesets = id(new DifferentialChangesetQuery())
        ->setViewer($request->getUser())
        ->withIDs($changeset_ids)
        ->execute();
      $changesets = mpull($changesets, null, 'getID');
    } else {
      $changesets = array();
    }

    foreach ($inlines as $key => $inline) {
      $inlines[$key] = DifferentialInlineComment::newFromModernComment(
        $inline);
    }

    $query = id(new DifferentialInlineCommentQuery())
      ->needHidden(true)
      ->setViewer($viewer);

    // NOTE: This is a bit sketchy: this method adjusts the inlines as a
    // side effect, which means it will ultimately adjust the transaction
    // comments and affect timeline rendering.
    $query->adjustInlinesForChangesets(
      $inlines,
      array_select_keys($changesets, $old_ids),
      array_select_keys($changesets, $new_ids),
      $this);

    return $timeline
      ->setChangesets($changesets)
      ->setRevision($this)
      ->setLeftDiff($left_diff)
      ->setRightDiff($right_diff);
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $diffs = id(new DifferentialDiffQuery())
        ->setViewer($engine->getViewer())
        ->withRevisionIDs(array($this->getID()))
        ->execute();
      foreach ($diffs as $diff) {
        $engine->destroyObject($diff);
      }

      $conn_w = $this->establishConnection('w');

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d',
        self::TABLE_COMMIT,
        $this->getID());

      // we have to do paths a little differentally as they do not have
      // an id or phid column for delete() to act on
      $dummy_path = new DifferentialAffectedPath();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d',
        $dummy_path->getTableName(),
        $this->getID());

      $this->delete();
    $this->saveTransaction();
  }

}
