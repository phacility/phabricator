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
    PhabricatorTimelineInterface,
    PhabricatorMentionableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface,
    PhabricatorConduitResultInterface,
    PhabricatorDraftInterface {

  protected $title = '';
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
  protected $activeDiffPHID;

  protected $viewPolicy = PhabricatorPolicies::POLICY_USER;
  protected $editPolicy = PhabricatorPolicies::POLICY_USER;
  protected $properties = array();

  private $commitPHIDs = self::ATTACHABLE;
  private $activeDiff = self::ATTACHABLE;
  private $diffIDs = self::ATTACHABLE;
  private $hashes = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  private $reviewerStatus = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $drafts = array();
  private $flags = array();
  private $forceMap = array();

  const RELATION_REVIEWER     = 'revw';
  const RELATION_SUBSCRIBED   = 'subd';

  const PROPERTY_CLOSED_FROM_ACCEPTED = 'wasAcceptedBeforeClose';
  const PROPERTY_DRAFT_HOLD = 'draft.hold';
  const PROPERTY_SHOULD_BROADCAST = 'draft.broadcast';
  const PROPERTY_LINES_ADDED = 'lines.added';
  const PROPERTY_LINES_REMOVED = 'lines.removed';
  const PROPERTY_BUILDABLES = 'buildables';
  const PROPERTY_WRONG_BUILDS = 'wrong.builds';

  public static function initializeNewRevision(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorDifferentialApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      DifferentialDefaultViewCapability::CAPABILITY);

    $initial_state = DifferentialRevisionStatus::DRAFT;
    $should_broadcast = false;

    return id(new DifferentialRevision())
      ->setViewPolicy($view_policy)
      ->setAuthorPHID($actor->getPHID())
      ->attachRepository(null)
      ->attachActiveDiff(null)
      ->attachReviewers(array())
      ->setModernRevisionStatus($initial_state)
      ->setShouldBroadcast($should_broadcast);
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
        'key_modified' => array(
          'columns' => array('dateModified'),
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

  public function getCommitPHIDs() {
    return $this->assertAttached($this->commitPHIDs);
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
    $this->commitPHIDs = $phids;
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

    // See T13657. We ignore "watcher" packages which don't grant their owners
    // permission to force accept anything.

    $authority_query = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
      ->withAuthorityModes(
        array(
          PhabricatorOwnersPackage::AUTHORITY_STRONG,
        ))
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
    // force-accept a package, we don't need to keep looking.
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

  public function hasAttachedReviewers() {
    return ($this->reviewerStatus !== self::ATTACHABLE);
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
    return $this->setStatus($status);
  }

  public function getModernRevisionStatus() {
    return $this->getStatus();
  }

  public function getLegacyRevisionStatus() {
    return $this->getStatusObject()->getLegacyKey();
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

  public function isDraft() {
    return $this->getStatusObject()->isDraft();
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

  public function getStatusTagColor() {
    return $this->getStatusObject()->getTagColor();
  }

  public function getStatusObject() {
    $status = $this->getStatus();
    return DifferentialRevisionStatus::newForStatus($status);
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

  public function getHoldAsDraft() {
    return $this->getProperty(self::PROPERTY_DRAFT_HOLD, false);
  }

  public function setHoldAsDraft($hold) {
    return $this->setProperty(self::PROPERTY_DRAFT_HOLD, $hold);
  }

  public function getShouldBroadcast() {
    return $this->getProperty(self::PROPERTY_SHOULD_BROADCAST, true);
  }

  public function setShouldBroadcast($should_broadcast) {
    return $this->setProperty(
      self::PROPERTY_SHOULD_BROADCAST,
      $should_broadcast);
  }

  public function setAddedLineCount($count) {
    return $this->setProperty(self::PROPERTY_LINES_ADDED, $count);
  }

  public function getAddedLineCount() {
    return $this->getProperty(self::PROPERTY_LINES_ADDED);
  }

  public function setRemovedLineCount($count) {
    return $this->setProperty(self::PROPERTY_LINES_REMOVED, $count);
  }

  public function getRemovedLineCount() {
    return $this->getProperty(self::PROPERTY_LINES_REMOVED);
  }

  public function hasLineCounts() {
    // This data was not populated on older revisions, so it may not be
    // present on all revisions.
    return isset($this->properties[self::PROPERTY_LINES_ADDED]);
  }

  public function getRevisionScaleGlyphs() {
    $add = $this->getAddedLineCount();
    $rem = $this->getRemovedLineCount();
    $all = ($add + $rem);

    if (!$all) {
      return '       ';
    }

    $map = array(
      20 => 2,
      50 => 3,
      150 => 4,
      375 => 5,
      1000 => 6,
      2500 => 7,
    );

    $n = 1;
    foreach ($map as $size => $count) {
      if ($size <= $all) {
        $n = $count;
      } else {
        break;
      }
    }

    $add_n = (int)ceil(($add / $all) * $n);
    $rem_n = (int)ceil(($rem / $all) * $n);

    while ($add_n + $rem_n > $n) {
      if ($add_n > 1) {
        $add_n--;
      } else {
        $rem_n--;
      }
    }

    return
      str_repeat('+', $add_n).
      str_repeat('-', $rem_n).
      str_repeat(' ', (7 - $n));
  }

  public function getBuildableStatus($phid) {
    $buildables = $this->getProperty(self::PROPERTY_BUILDABLES);
    if (!is_array($buildables)) {
      $buildables = array();
    }

    $buildable = idx($buildables, $phid);
    if (!is_array($buildable)) {
      $buildable = array();
    }

    return idx($buildable, 'status');
  }

  public function setBuildableStatus($phid, $status) {
    $buildables = $this->getProperty(self::PROPERTY_BUILDABLES);
    if (!is_array($buildables)) {
      $buildables = array();
    }

    $buildable = idx($buildables, $phid);
    if (!is_array($buildable)) {
      $buildable = array();
    }

    $buildable['status'] = $status;

    $buildables[$phid] = $buildable;

    return $this->setProperty(self::PROPERTY_BUILDABLES, $buildables);
  }

  public function newBuildableStatus(PhabricatorUser $viewer, $phid) {
    // For Differential, we're ignoring autobuilds (local lint and unit)
    // when computing build status. Differential only cares about remote
    // builds when making publishing and undrafting decisions.

    $builds = $this->loadImpactfulBuildsForBuildablePHIDs(
      $viewer,
      array($phid));

    return $this->newBuildableStatusForBuilds($builds);
  }

  public function newBuildableStatusForBuilds(array $builds) {
    // If we have nothing but passing builds, the buildable passes.
    if (!$builds) {
      return HarbormasterBuildableStatus::STATUS_PASSED;
    }

    // If we have any completed, non-passing builds, the buildable fails.
    foreach ($builds as $build) {
      if ($build->isComplete()) {
        return HarbormasterBuildableStatus::STATUS_FAILED;
      }
    }

    // Otherwise, we're still waiting for the build to pass or fail.
    return null;
  }

  public function loadImpactfulBuilds(PhabricatorUser $viewer) {
    $diff = $this->getActiveDiff();

    // NOTE: We can't use `withContainerPHIDs()` here because the container
    // update in Harbormaster is not synchronous.
    $buildables = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($diff->getPHID()))
      ->withManualBuildables(false)
      ->execute();
    if (!$buildables) {
      return array();
    }

    return $this->loadImpactfulBuildsForBuildablePHIDs(
      $viewer,
      mpull($buildables, 'getPHID'));
  }

  private function loadImpactfulBuildsForBuildablePHIDs(
    PhabricatorUser $viewer,
    array $phids) {

    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs($phids)
      ->withAutobuilds(false)
      ->withBuildStatuses(
        array(
          HarbormasterBuildStatus::STATUS_INACTIVE,
          HarbormasterBuildStatus::STATUS_PENDING,
          HarbormasterBuildStatus::STATUS_BUILDING,
          HarbormasterBuildStatus::STATUS_FAILED,
          HarbormasterBuildStatus::STATUS_ABORTED,
          HarbormasterBuildStatus::STATUS_ERROR,
          HarbormasterBuildStatus::STATUS_PAUSED,
          HarbormasterBuildStatus::STATUS_DEADLOCKED,
        ))
      ->execute();

    // Filter builds based on the "Hold Drafts" behavior of their associated
    // build plans.

    $hold_drafts = HarbormasterBuildPlanBehavior::BEHAVIOR_DRAFTS;
    $behavior = HarbormasterBuildPlanBehavior::getBehavior($hold_drafts);

    $key_never = HarbormasterBuildPlanBehavior::DRAFTS_NEVER;
    $key_building = HarbormasterBuildPlanBehavior::DRAFTS_IF_BUILDING;

    foreach ($builds as $key => $build) {
      $plan = $build->getBuildPlan();

      // See T13526. If the viewer can't see the build plan, pretend it has
      // generic options. This is often wrong, but "often wrong" is better than
      // "fatal".
      if ($plan) {
        $hold_key = $behavior->getPlanOption($plan)->getKey();

        $hold_never = ($hold_key === $key_never);
        $hold_building = ($hold_key === $key_building);
      } else {
        $hold_never = false;
        $hold_building = false;
      }

      // If the build "Never" holds drafts from promoting, we don't care what
      // the status is.
      if ($hold_never) {
        unset($builds[$key]);
        continue;
      }

      // If the build holds drafts from promoting "While Building", we only
      // care about the status until it completes.
      if ($hold_building) {
        if ($build->isComplete()) {
          unset($builds[$key]);
          continue;
        }
      }
    }

    return $builds;
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

  public function getBuildVariables() {
    return array();
  }

  public function getAvailableBuildVariables() {
    return array();
  }

  public function newBuildableEngine() {
    return new DifferentialBuildableEngine();
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
      if ($reviewer->getReviewerPHID() !== $phid) {
        continue;
      }

      if ($reviewer->isResigned()) {
        continue;
      }

      return true;
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

  public function getApplicationTransactionTemplate() {
    return new DifferentialTransaction();
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $viewer = $engine->getViewer();

    $this->openTransaction();
      $diffs = id(new DifferentialDiffQuery())
        ->setViewer($viewer)
        ->withRevisionIDs(array($this->getID()))
        ->execute();
      foreach ($diffs as $diff) {
        $engine->destroyObject($diff);
      }

      id(new DifferentialAffectedPathEngine())
        ->setRevision($this)
        ->destroyAffectedPaths();

      $viewstate_query = id(new DifferentialViewStateQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($this->getPHID()));
      $viewstates = new PhabricatorQueryIterator($viewstate_query);
      foreach ($viewstates as $viewstate) {
        $viewstate->delete();
      }

      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new DifferentialRevisionFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new DifferentialRevisionFerretEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('title')
        ->setType('string')
        ->setDescription(pht('The revision title.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('uri')
        ->setType('uri')
        ->setDescription(pht('View URI for the revision.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authorPHID')
        ->setType('phid')
        ->setDescription(pht('Revision author PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about revision status.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('repositoryPHID')
        ->setType('phid?')
        ->setDescription(pht('Revision repository PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('diffPHID')
        ->setType('phid')
        ->setDescription(pht('Active diff PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('summary')
        ->setType('string')
        ->setDescription(pht('Revision summary.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('testPlan')
        ->setType('string')
        ->setDescription(pht('Revision test plan.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isDraft')
        ->setType('bool')
        ->setDescription(
          pht(
            'True if this revision is in any draft state, and thus not '.
            'notifying reviewers and subscribers about changes.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('holdAsDraft')
        ->setType('bool')
        ->setDescription(
          pht(
            'True if this revision is being held as a draft. It will not be '.
            'automatically submitted for review even if tests pass.')),
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
      'uri' => PhabricatorEnv::getURI($this->getURI()),
      'authorPHID' => $this->getAuthorPHID(),
      'status' => $status_info,
      'repositoryPHID' => $this->getRepositoryPHID(),
      'diffPHID' => $this->getActiveDiffPHID(),
      'summary' => $this->getSummary(),
      'testPlan' => $this->getTestPlan(),
      'isDraft' => !$this->getShouldBroadcast(),
      'holdAsDraft' => (bool)$this->getHoldAsDraft(),
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


/* -(  PhabricatorTimelineInterface  )--------------------------------------- */


  public function newTimelineEngine() {
    return new DifferentialRevisionTimelineEngine();
  }


}
