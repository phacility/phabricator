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
    PhabricatorProjectInterface,
    PhabricatorFulltextInterface,
    PhabricatorConduitResultInterface,
    PhabricatorDraftInterface {

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
  protected $repositoryPHID;
  protected $viewPolicy = PhabricatorPolicies::POLICY_USER;
  protected $editPolicy = PhabricatorPolicies::POLICY_USER;
  protected $properties = array();

  private $commits = self::ATTACHABLE;
  private $activeDiff = self::ATTACHABLE;
  private $diffIDs = self::ATTACHABLE;
  private $hashes = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  private $reviewerStatus = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $drafts = array();
  private $flags = array();
  private $forceMap = array();

  const TABLE_COMMIT          = 'differential_commit';

  const RELATION_REVIEWER     = 'revw';
  const RELATION_SUBSCRIBED   = 'subd';

  const PROPERTY_CLOSED_FROM_ACCEPTED = 'wasAcceptedBeforeClose';

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
      ->attachRepository(null)
      ->attachActiveDiff(null)
      ->attachReviewers(array())
      ->setModernRevisionStatus(DifferentialRevisionStatus::NEEDS_REVIEW);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attached'      => self::SERIALIZATION_JSON,
        'unsubscribed'  => self::SERIALIZATION_JSON,
        'properties' => self::SERIALIZATION_JSON,
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
        // If you (or a project you are a member of) is reviewing a significant
        // fraction of the revisions on an install, the result set of open
        // revisions may be smaller than the result set of revisions where you
        // are a reviewer. In these cases, this key is better than keys on the
        // edge table.
        'key_status' => array(
          'columns' => array('status', 'phid'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function hasRevisionProperty($key) {
    return array_key_exists($key, $this->properties);
  }

  public function getMonogram() {
    $id = $this->getID();
    return "D{$id}";
  }

  public function getURI() {
    return '/'.$this->getMonogram();
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

  public function getHashes() {
    return $this->assertAttached($this->hashes);
  }

  public function attachHashes(array $hashes) {
    $this->hashes = $hashes;
    return $this;
  }

  public function canReviewerForceAccept(
    PhabricatorUser $viewer,
    DifferentialReviewer $reviewer) {

    if (!$reviewer->isPackage()) {
      return false;
    }

    $map = $this->getReviewerForceAcceptMap($viewer);
    if (!$map) {
      return false;
    }

    if (isset($map[$reviewer->getReviewerPHID()])) {
      return true;
    }

    return false;
  }

  private function getReviewerForceAcceptMap(PhabricatorUser $viewer) {
    $fragment = $viewer->getCacheFragment();

    if (!array_key_exists($fragment, $this->forceMap)) {
      $map = $this->newReviewerForceAcceptMap($viewer);
      $this->forceMap[$fragment] = $map;
    }

    return $this->forceMap[$fragment];
  }

  private function newReviewerForceAcceptMap(PhabricatorUser $viewer) {
    $diff = $this->getActiveDiff();
    if (!$diff) {
      return null;
    }

    $repository_phid = $diff->getRepositoryPHID();
    if (!$repository_phid) {
      return null;
    }

    $paths = array();

    try {
      $changesets = $diff->getChangesets();
    } catch (Exception $ex) {
      $changesets = id(new DifferentialChangesetQuery())
        ->setViewer($viewer)
        ->withDiffs(array($diff))
        ->execute();
    }

    foreach ($changesets as $changeset) {
      $paths[] = $changeset->getOwnersFilename();
    }

    if (!$paths) {
      return null;
    }

    $reviewer_phids = array();
    foreach ($this->getReviewers() as $reviewer) {
      if (!$reviewer->isPackage()) {
        continue;
      }

      $reviewer_phids[] = $reviewer->getReviewerPHID();
    }

    if (!$reviewer_phids) {
      return null;
    }

    // Load all the reviewing packages which have control over some of the
    // paths in the change. These are packages which the actor may be able
    // to force-accept on behalf of.
    $control_query = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
      ->withPHIDs($reviewer_phids)
      ->withControl($repository_phid, $paths);
    $control_packages = $control_query->execute();
    if (!$control_packages) {
      return null;
    }

    // Load all the packages which have potential control over some of the
    // paths in the change and are owned by the actor. These are packages
    // which the actor may be able to use their authority over to gain the
    // ability to force-accept for other packages. This query doesn't apply
    // dominion rules yet, and we'll bypass those rules later on.
    $authority_query = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
      ->withAuthorityPHIDs(array($viewer->getPHID()))
      ->withControl($repository_phid, $paths);
    $authority_packages = $authority_query->execute();
    if (!$authority_packages) {
      return null;
    }
    $authority_packages = mpull($authority_packages, null, 'getPHID');

    // Build a map from each path in the revision to the reviewer packages
    // which control it.
    $control_map = array();
    foreach ($paths as $path) {
      $control_packages = $control_query->getControllingPackagesForPath(
        $repository_phid,
        $path);

      // Remove packages which the viewer has authority over. We don't need
      // to check these for force-accept because they can just accept them
      // normally.
      $control_packages = mpull($control_packages, null, 'getPHID');
      foreach ($control_packages as $phid => $control_package) {
        if (isset($authority_packages[$phid])) {
          unset($control_packages[$phid]);
        }
      }

      if (!$control_packages) {
        continue;
      }

      $control_map[$path] = $control_packages;
    }

    if (!$control_map) {
      return null;
    }

    // From here on out, we only care about paths which we have at least one
    // controlling package for.
    $paths = array_keys($control_map);

    // Now, build a map from each path to the packages which would control it
    // if there were no dominion rules.
    $authority_map = array();
    foreach ($paths as $path) {
      $authority_packages = $authority_query->getControllingPackagesForPath(
        $repository_phid,
        $path,
        $ignore_dominion = true);

      $authority_map[$path] = mpull($authority_packages, null, 'getPHID');
    }

    // For each path, find the most general package that the viewer has
    // authority over. For example, we'll prefer a package that owns "/" to a
    // package that owns "/src/".
    $force_map = array();
    foreach ($authority_map as $path => $package_map) {
      $path_fragments = PhabricatorOwnersPackage::splitPath($path);
      $fragment_count = count($path_fragments);

      // Find the package that we have authority over which has the most
      // general match for this path.
      $best_match = null;
      $best_package = null;
      foreach ($package_map as $package_phid => $package) {
        $package_paths = $package->getPathsForRepository($repository_phid);
        foreach ($package_paths as $package_path) {

          // NOTE: A strength of 0 means "no match". A strength of 1 means
          // that we matched "/", so we can not possibly find another stronger
          // match.

          $strength = $package_path->getPathMatchStrength(
            $path_fragments,
            $fragment_count);
          if (!$strength) {
            continue;
          }

          if ($strength < $best_match || !$best_package) {
            $best_match = $strength;
            $best_package = $package;
            if ($strength == 1) {
              break 2;
            }
          }
        }
      }

      if ($best_package) {
        $force_map[$path] = array(
          'strength' => $best_match,
          'package' => $best_package,
        );
      }
    }

    // For each path which the viewer owns a package for, find other packages
    // which that authority can be used to force-accept. Once we find a way to
    // force-accept a package, we don't need to keep loooking.
    $has_control = array();
    foreach ($force_map as $path => $spec) {
      $path_fragments = PhabricatorOwnersPackage::splitPath($path);
      $fragment_count = count($path_fragments);

      $authority_strength = $spec['strength'];

      $control_packages = $control_map[$path];
      foreach ($control_packages as $control_phid => $control_package) {
        if (isset($has_control[$control_phid])) {
          continue;
        }

        $control_paths = $control_package->getPathsForRepository(
          $repository_phid);
        foreach ($control_paths as $control_path) {
          $strength = $control_path->getPathMatchStrength(
            $path_fragments,
            $fragment_count);

          if (!$strength) {
            continue;
          }

          if ($strength > $authority_strength) {
            $authority = $spec['package'];
            $has_control[$control_phid] = array(
              'authority' => $authority,
              'phid' => $authority->getPHID(),
            );
            break;
          }
        }
      }
    }

    // Return a map from packages which may be force accepted to the packages
    // which permit that forced acceptance.
    return ipull($has_control, 'phid');
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

  public function getReviewers() {
    return $this->assertAttached($this->reviewerStatus);
  }

  public function attachReviewers(array $reviewers) {
    assert_instances_of($reviewers, 'DifferentialReviewer');
    $reviewers = mpull($reviewers, null, 'getReviewerPHID');
    $this->reviewerStatus = $reviewers;
    return $this;
  }

  public function getReviewerPHIDs() {
    $reviewers = $this->getReviewers();
    return mpull($reviewers, 'getReviewerPHID');
  }

  public function getReviewerPHIDsForEdit() {
    $reviewers = $this->getReviewers();

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    $value = array();
    foreach ($reviewers as $reviewer) {
      $phid = $reviewer->getReviewerPHID();
      if ($reviewer->getReviewerStatus() == $status_blocking) {
        $value[] = 'blocking('.$phid.')';
      } else {
        $value[] = $phid;
      }
    }

    return $value;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachRepository(PhabricatorRepository $repository = null) {
    $this->repository = $repository;
    return $this;
  }

  public function setModernRevisionStatus($status) {
    $status_object = DifferentialRevisionStatus::newForStatus($status);

    if ($status_object->getKey() != $status) {
      throw new Exception(
        pht(
          'Trying to set revision to invalid status "%s".',
          $status));
    }

    $legacy_status = $status_object->getLegacyKey();

    return $this->setStatus($legacy_status);
  }

  public function getModernRevisionStatus() {
    return $this->getStatusObject()->getKey();
  }

  public function getLegacyRevisionStatus() {
    return $this->getStatus();
  }

  public function isClosed() {
    return $this->getStatusObject()->isClosedStatus();
  }

  public function isAbandoned() {
    return $this->getStatusObject()->isAbandoned();
  }

  public function isAccepted() {
    return $this->getStatusObject()->isAccepted();
  }

  public function isNeedsReview() {
    return $this->getStatusObject()->isNeedsReview();
  }

  public function isNeedsRevision() {
    return $this->getStatusObject()->isNeedsRevision();
  }

  public function isChangePlanned() {
    return $this->getStatusObject()->isChangePlanned();
  }

  public function isPublished() {
    return $this->getStatusObject()->isPublished();
  }

  public function getStatusIcon() {
    return $this->getStatusObject()->getIcon();
  }

  public function getStatusDisplayName() {
    return $this->getStatusObject()->getDisplayName();
  }

  public function getStatusIconColor() {
    return $this->getStatusObject()->getIconColor();
  }

  public function getStatusObject() {
    $status = $this->getStatus();
    return DifferentialRevisionStatus::newForLegacyStatus($status);
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

  public function getHasDraft(PhabricatorUser $viewer) {
    return $this->assertAttachedKey($this->drafts, $viewer->getCacheFragment());
  }

  public function attachHasDraft(PhabricatorUser $viewer, $has_draft) {
    $this->drafts[$viewer->getCacheFragment()] = $has_draft;
    return $this;
  }


/* -(  HarbormasterBuildableInterface  )------------------------------------- */


  public function getHarbormasterBuildableDisplayPHID() {
    return $this->getHarbormasterContainerPHID();
  }

  public function getHarbormasterBuildablePHID() {
    return $this->loadActiveDiff()->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getPHID();
  }

  public function getHarbormasterPublishablePHID() {
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
        ->needReviewers(true)
        ->executeOne()
        ->getReviewers();
    } else {
      $reviewers = $this->getReviewers();
    }

    foreach ($reviewers as $reviewer) {
      if ($reviewer->getReviewerPHID() == $phid) {
        return true;
      }
    }

    return false;
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


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new DifferentialRevisionFulltextEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('title')
        ->setType('string')
        ->setDescription(pht('The revision title.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authorPHID')
        ->setType('phid')
        ->setDescription(pht('Revision author PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about revision status.')),
    );
  }

  public function getFieldValuesForConduit() {
    $status = $this->getStatusObject();
    $status_info = array(
      'value' => $status->getKey(),
      'name' => $status->getDisplayName(),
      'closed' => $status->isClosedStatus(),
      'color.ansi' => $status->getANSIColor(),
    );

    return array(
      'title' => $this->getTitle(),
      'authorPHID' => $this->getAuthorPHID(),
      'status' => $status_info,
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new DifferentialReviewersSearchEngineAttachment())
        ->setAttachmentKey('reviewers'),
    );
  }


/* -(  PhabricatorDraftInterface  )------------------------------------------ */


  public function newDraftEngine() {
    return new DifferentialRevisionDraftEngine();
  }

}
