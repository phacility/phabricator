<?php

final class DifferentialDiff
  extends DifferentialDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    HarbormasterBuildableInterface,
    HarbormasterCircleCIBuildableInterface,
    HarbormasterBuildkiteBuildableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface {

  protected $revisionID;
  protected $authorPHID;
  protected $repositoryPHID;
  protected $commitPHID;

  protected $sourceMachine;
  protected $sourcePath;

  protected $sourceControlSystem;
  protected $sourceControlBaseRevision;
  protected $sourceControlPath;

  protected $lintStatus;
  protected $unitStatus;

  protected $lineCount;

  protected $branch;
  protected $bookmark;

  protected $creationMethod;
  protected $repositoryUUID;

  protected $description;

  protected $viewPolicy;

  private $unsavedChangesets = array();
  private $changesets = self::ATTACHABLE;
  private $revision = self::ATTACHABLE;
  private $properties = array();
  private $buildable = self::ATTACHABLE;

  private $unitMessages = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'revisionID' => 'id?',
        'authorPHID' => 'phid?',
        'repositoryPHID' => 'phid?',
        'sourceMachine' => 'text255?',
        'sourcePath' => 'text255?',
        'sourceControlSystem' => 'text64?',
        'sourceControlBaseRevision' => 'text255?',
        'sourceControlPath' => 'text255?',
        'lintStatus' => 'uint32',
        'unitStatus' => 'uint32',
        'lineCount' => 'uint32',
        'branch' => 'text255?',
        'bookmark' => 'text255?',
        'repositoryUUID' => 'text64?',
        'commitPHID' => 'phid?',

        // T6203/NULLABILITY
        // These should be non-null; all diffs should have a creation method
        // and the description should just be empty.
        'creationMethod' => 'text255?',
        'description' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'revisionID' => array(
          'columns' => array('revisionID'),
        ),
        'key_commit' => array(
          'columns' => array('commitPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      DifferentialDiffPHIDType::TYPECONST);
  }

  public function addUnsavedChangeset(DifferentialChangeset $changeset) {
    if ($this->changesets === null) {
      $this->changesets = array();
    }
    $this->unsavedChangesets[] = $changeset;
    $this->changesets[] = $changeset;
    return $this;
  }

  public function attachChangesets(array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');
    $this->changesets = $changesets;
    return $this;
  }

  public function getChangesets() {
    return $this->assertAttached($this->changesets);
  }

  public function loadChangesets() {
    if (!$this->getID()) {
      return array();
    }
    $changesets = id(new DifferentialChangeset())->loadAllWhere(
      'diffID = %d',
      $this->getID());

    foreach ($changesets as $changeset) {
      $changeset->attachDiff($this);
    }

    return $changesets;
  }

  public function save() {
    $this->openTransaction();
      $ret = parent::save();
      foreach ($this->unsavedChangesets as $changeset) {
        $changeset->setDiffID($this->getID());
        $changeset->save();
      }
    $this->saveTransaction();
    return $ret;
  }

  public static function initializeNewDiff(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorDifferentialApplication'))
      ->executeOne();
    $view_policy = $app->getPolicy(
      DifferentialDefaultViewCapability::CAPABILITY);

    $diff = id(new DifferentialDiff())
      ->setViewPolicy($view_policy);

    return $diff;
  }

  public static function newFromRawChanges(
    PhabricatorUser $actor,
    array $changes) {

    assert_instances_of($changes, 'ArcanistDiffChange');

    $diff = self::initializeNewDiff($actor);
    return self::buildChangesetsFromRawChanges($diff, $changes);
  }

  public static function newEphemeralFromRawChanges(array $changes) {
    assert_instances_of($changes, 'ArcanistDiffChange');

    $diff = id(new DifferentialDiff())->makeEphemeral();
    return self::buildChangesetsFromRawChanges($diff, $changes);
  }

  private static function buildChangesetsFromRawChanges(
    DifferentialDiff $diff,
    array $changes) {

    // There may not be any changes; initialize the changesets list so that
    // we don't throw later when accessing it.
    $diff->attachChangesets(array());

    $lines = 0;
    foreach ($changes as $change) {
      if ($change->getType() == ArcanistDiffChangeType::TYPE_MESSAGE) {
        // If a user pastes a diff into Differential which includes a commit
        // message (e.g., they ran `git show` to generate it), discard that
        // change when constructing a DifferentialDiff.
        continue;
      }

      $changeset = new DifferentialChangeset();
      $add_lines = 0;
      $del_lines = 0;
      $first_line = PHP_INT_MAX;
      $hunks = $change->getHunks();
      if ($hunks) {
        foreach ($hunks as $hunk) {
          $dhunk = new DifferentialHunk();
          $dhunk->setOldOffset($hunk->getOldOffset());
          $dhunk->setOldLen($hunk->getOldLength());
          $dhunk->setNewOffset($hunk->getNewOffset());
          $dhunk->setNewLen($hunk->getNewLength());
          $dhunk->setChanges($hunk->getCorpus());
          $changeset->addUnsavedHunk($dhunk);
          $add_lines += $hunk->getAddLines();
          $del_lines += $hunk->getDelLines();
          $added_lines = $hunk->getChangedLines('new');
          if ($added_lines) {
            $first_line = min($first_line, head_key($added_lines));
          }
        }
        $lines += $add_lines + $del_lines;
      } else {
        // This happens when you add empty files.
        $changeset->attachHunks(array());
      }

      $metadata = $change->getAllMetadata();
      if ($first_line != PHP_INT_MAX) {
        $metadata['line:first'] = $first_line;
      }

      $changeset->setOldFile($change->getOldPath());
      $changeset->setFilename($change->getCurrentPath());
      $changeset->setChangeType($change->getType());

      $changeset->setFileType($change->getFileType());
      $changeset->setMetadata($metadata);
      $changeset->setOldProperties($change->getOldProperties());
      $changeset->setNewProperties($change->getNewProperties());
      $changeset->setAwayPaths($change->getAwayPaths());
      $changeset->setAddLines($add_lines);
      $changeset->setDelLines($del_lines);

      $diff->addUnsavedChangeset($changeset);
    }
    $diff->setLineCount($lines);

    $changesets = $diff->getChangesets();

    // TODO: This is "safe", but it would be better to propagate a real user
    // down the stack.
    $viewer = PhabricatorUser::getOmnipotentUser();

    id(new DifferentialChangesetEngine())
      ->setViewer($viewer)
      ->rebuildChangesets($changesets);

    return $diff;
  }

  public function getDiffDict() {
    $dict = array(
      'id' => $this->getID(),
      'revisionID' => $this->getRevisionID(),
      'dateCreated' => $this->getDateCreated(),
      'dateModified' => $this->getDateModified(),
      'sourceControlBaseRevision' => $this->getSourceControlBaseRevision(),
      'sourceControlPath' => $this->getSourceControlPath(),
      'sourceControlSystem' => $this->getSourceControlSystem(),
      'branch' => $this->getBranch(),
      'bookmark' => $this->getBookmark(),
      'creationMethod' => $this->getCreationMethod(),
      'description' => $this->getDescription(),
      'unitStatus' => $this->getUnitStatus(),
      'lintStatus' => $this->getLintStatus(),
      'changes' => array(),
    );

    $dict['changes'] = $this->buildChangesList();

    return $dict + $this->getDiffAuthorshipDict();
  }

  public function getDiffAuthorshipDict() {
    $dict = array('properties' => array());

    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $this->getID());
    foreach ($properties as $property) {
      $dict['properties'][$property->getName()] = $property->getData();

      if ($property->getName() == 'local:commits') {
        foreach ($property->getData() as $commit) {
          $dict['authorName'] = $commit['author'];
          $dict['authorEmail'] = idx($commit, 'authorEmail');
          break;
        }
      }
    }

    return $dict;
  }

  public function buildChangesList() {
    $changes = array();
    foreach ($this->getChangesets() as $changeset) {
      $hunks = array();
      foreach ($changeset->getHunks() as $hunk) {
        $hunks[] = array(
          'oldOffset' => $hunk->getOldOffset(),
          'newOffset' => $hunk->getNewOffset(),
          'oldLength' => $hunk->getOldLen(),
          'newLength' => $hunk->getNewLen(),
          'addLines'  => null,
          'delLines'  => null,
          'isMissingOldNewline' => null,
          'isMissingNewNewline' => null,
          'corpus'    => $hunk->getChanges(),
        );
      }
      $change = array(
        'id'            => $changeset->getID(),
        'metadata'      => $changeset->getMetadata(),
        'oldPath'       => $changeset->getOldFile(),
        'currentPath'   => $changeset->getFilename(),
        'awayPaths'     => $changeset->getAwayPaths(),
        'oldProperties' => $changeset->getOldProperties(),
        'newProperties' => $changeset->getNewProperties(),
        'type'          => $changeset->getChangeType(),
        'fileType'      => $changeset->getFileType(),
        'commitHash'    => null,
        'addLines'      => $changeset->getAddLines(),
        'delLines'      => $changeset->getDelLines(),
        'hunks'         => $hunks,
      );
      $changes[] = $change;
    }
    return $changes;
  }

  public function hasRevision() {
    return $this->revision !== self::ATTACHABLE;
  }

  public function getRevision() {
    return $this->assertAttached($this->revision);
  }

  public function attachRevision(DifferentialRevision $revision = null) {
    $this->revision = $revision;
    return $this;
  }

  public function attachProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key) {
    return $this->assertAttachedKey($this->properties, $key);
  }

  public function hasDiffProperty($key) {
    $properties = $this->getDiffProperties();
    return array_key_exists($key, $properties);
  }

  public function attachDiffProperties(array $properties) {
    $this->properties = $properties;
    return $this;
  }

  public function getDiffProperties() {
    return $this->assertAttached($this->properties);
  }

  public function attachBuildable(HarbormasterBuildable $buildable = null) {
    $this->buildable = $buildable;
    return $this;
  }

  public function getBuildable() {
    return $this->assertAttached($this->buildable);
  }

  public function getBuildTargetPHIDs() {
    $buildable = $this->getBuildable();

    if (!$buildable) {
      return array();
    }

    $target_phids = array();
    foreach ($buildable->getBuilds() as $build) {
      foreach ($build->getBuildTargets() as $target) {
        $target_phids[] = $target->getPHID();
      }
    }

    return $target_phids;
  }

  public function loadCoverageMap(PhabricatorUser $viewer) {
    $target_phids = $this->getBuildTargetPHIDs();
    if (!$target_phids) {
      return array();
    }

    $unit = id(new HarbormasterBuildUnitMessageQuery())
      ->setViewer($viewer)
      ->withBuildTargetPHIDs($target_phids)
      ->execute();

    $map = array();
    foreach ($unit as $message) {
      $coverage = $message->getProperty('coverage', array());
      foreach ($coverage as $path => $coverage_data) {
        $map[$path][] = $coverage_data;
      }
    }

    foreach ($map as $path => $coverage_items) {
      $map[$path] = ArcanistUnitTestResult::mergeCoverage($coverage_items);
    }

    return $map;
  }

  public function getURI() {
    $id = $this->getID();
    return "/differential/diff/{$id}/";
  }


  public function attachUnitMessages(array $unit_messages) {
    $this->unitMessages = $unit_messages;
    return $this;
  }


  public function getUnitMessages() {
    return $this->assertAttached($this->unitMessages);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    if ($this->hasRevision()) {
      return PhabricatorPolicies::getMostOpenPolicy();
    }

    return $this->viewPolicy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->hasRevision()) {
      return $this->getRevision()->hasAutomaticCapability($capability, $viewer);
    }

    return ($this->getAuthorPHID() == $viewer->getPHID());
  }

  public function describeAutomaticCapability($capability) {
    if ($this->hasRevision()) {
      return pht(
        'This diff is attached to a revision, and inherits its policies.');
    }

    return pht('The author of a diff can see it.');
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->hasRevision()) {
          $extended[] = array(
            $this->getRevision(),
            PhabricatorPolicyCapability::CAN_VIEW,
          );
        } else if ($this->getRepositoryPHID()) {
          $extended[] = array(
            $this->getRepositoryPHID(),
            PhabricatorPolicyCapability::CAN_VIEW,
          );
        }
        break;
    }

    return $extended;
  }


/* -(  HarbormasterBuildableInterface  )------------------------------------- */


  public function getHarbormasterBuildableDisplayPHID() {
    $container_phid = $this->getHarbormasterContainerPHID();
    if ($container_phid) {
      return $container_phid;
    }

    return $this->getHarbormasterBuildablePHID();
  }

  public function getHarbormasterBuildablePHID() {
    return $this->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    if ($this->getRevisionID()) {
      $revision = id(new DifferentialRevision())->load($this->getRevisionID());
      if ($revision) {
        return $revision->getPHID();
      }
    }

    return null;
  }

  public function getBuildVariables() {
    $results = array();

    $results['buildable.diff'] = $this->getID();
    if ($this->revisionID) {
      $revision = $this->getRevision();
      $results['buildable.revision'] = $revision->getID();
      $repo = $revision->getRepository();

      if ($repo) {
        $results['repository.callsign'] = $repo->getCallsign();
        $results['repository.phid'] = $repo->getPHID();
        $results['repository.vcs'] = $repo->getVersionControlSystem();
        $results['repository.uri'] = $repo->getPublicCloneURI();

        $results['repository.staging.uri'] = $repo->getStagingURI();
        $results['repository.staging.ref'] = $this->getStagingRef();
      }
    }

    return $results;
  }

  public function getAvailableBuildVariables() {
    return array(
      'buildable.diff' =>
        pht('The differential diff ID, if applicable.'),
      'buildable.revision' =>
        pht('The differential revision ID, if applicable.'),
      'repository.callsign' =>
        pht('The callsign of the repository in Phabricator.'),
      'repository.phid' =>
        pht('The PHID of the repository in Phabricator.'),
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
      'repository.staging.uri' =>
        pht('The URI of the staging repository.'),
      'repository.staging.ref' =>
        pht('The ref name for this change in the staging repository.'),
    );
  }

  public function newBuildableEngine() {
    return new DifferentialBuildableEngine();
  }


/* -(  HarbormasterCircleCIBuildableInterface  )----------------------------- */


  public function getCircleCIGitHubRepositoryURI() {
    $diff_phid = $this->getPHID();
    $repository_phid = $this->getRepositoryPHID();
    if (!$repository_phid) {
      throw new Exception(
        pht(
          'This diff ("%s") is not associated with a repository. A diff '.
          'must belong to a tracked repository to be built by CircleCI.',
          $diff_phid));
    }

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($repository_phid))
      ->executeOne();
    if (!$repository) {
      throw new Exception(
        pht(
          'This diff ("%s") is associated with a repository ("%s") which '.
          'could not be loaded.',
          $diff_phid,
          $repository_phid));
    }

    $staging_uri = $repository->getStagingURI();
    if (!$staging_uri) {
      throw new Exception(
        pht(
          'This diff ("%s") is associated with a repository ("%s") that '.
          'does not have a Staging Area configured. You must configure a '.
          'Staging Area to use CircleCI integration.',
          $diff_phid,
          $repository_phid));
    }

    $path = HarbormasterCircleCIBuildStepImplementation::getGitHubPath(
      $staging_uri);
    if (!$path) {
      throw new Exception(
        pht(
          'This diff ("%s") is associated with a repository ("%s") that '.
          'does not have a Staging Area ("%s") that is hosted on GitHub. '.
          'CircleCI can only build from GitHub, so the Staging Area for '.
          'the repository must be hosted there.',
          $diff_phid,
          $repository_phid,
          $staging_uri));
    }

    return $staging_uri;
  }

  public function getCircleCIBuildIdentifierType() {
    return 'tag';
  }

  public function getCircleCIBuildIdentifier() {
    $ref = $this->getStagingRef();
    $ref = preg_replace('(^refs/tags/)', '', $ref);
    return $ref;
  }


/* -(  HarbormasterBuildkiteBuildableInterface  )---------------------------- */

  public function getBuildkiteBranch() {
    $ref = $this->getStagingRef();

    // NOTE: Circa late January 2017, Buildkite fails with the error message
    // "Tags have been disabled for this project" if we pass the "refs/tags/"
    // prefix via the API and the project doesn't have GitHub tag builds
    // enabled, even if GitHub builds are disabled. The tag builds fine
    // without this prefix.
    $ref = preg_replace('(^refs/tags/)', '', $ref);

    return $ref;
  }

  public function getBuildkiteCommit() {
    return 'HEAD';
  }


  public function getStagingRef() {
    // TODO: We're just hoping to get lucky. Instead, `arc` should store
    // where it sent changes and we should only provide staging details
    // if we reasonably believe they are accurate.
    return 'refs/tags/phabricator/diff/'.$this->getID();
  }

  public function loadTargetBranch() {
    // TODO: This is sketchy, but just eat the query cost until this can get
    // cleaned up.

    // For now, we're only returning a target if there's exactly one and it's
    // a branch, since we don't support landing to more esoteric targets like
    // tags yet.

    $property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $this->getID(),
      'arc:onto');
    if (!$property) {
      return null;
    }

    $data = $property->getData();

    if (!$data) {
      return null;
    }

    if (!is_array($data)) {
      return null;
    }

    if (count($data) != 1) {
      return null;
    }

    $onto = head($data);
    if (!is_array($onto)) {
      return null;
    }

    $type = idx($onto, 'type');
    if ($type != 'branch') {
      return null;
    }

    return idx($onto, 'name');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DifferentialDiffEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new DifferentialDiffTransaction();
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $viewer = $engine->getViewer();

    $this->openTransaction();
      $this->delete();

      foreach ($this->loadChangesets() as $changeset) {
        $engine->destroyObject($changeset);
      }

      $properties = id(new DifferentialDiffProperty())->loadAllWhere(
        'diffID = %d',
        $this->getID());
      foreach ($properties as $prop) {
        $prop->delete();
      }

      $viewstates = id(new DifferentialViewStateQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($this->getPHID()));
      foreach ($viewstates as $viewstate) {
        $viewstate->delete();
      }

    $this->saveTransaction();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('revisionPHID')
        ->setType('phid')
        ->setDescription(pht('Associated revision PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authorPHID')
        ->setType('phid')
        ->setDescription(pht('Revision author PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('repositoryPHID')
        ->setType('phid')
        ->setDescription(pht('Associated repository PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('refs')
        ->setType('map<string, wild>')
        ->setDescription(pht('List of related VCS references.')),
    );
  }

  public function getFieldValuesForConduit() {
    $refs = array();

    $branch = $this->getBranch();
    if (strlen($branch)) {
      $refs[] = array(
        'type' => 'branch',
        'name' => $branch,
      );
    }

    $onto = $this->loadTargetBranch();
    if (strlen($onto)) {
      $refs[] = array(
        'type' => 'onto',
        'name' => $onto,
      );
    }

    $base = $this->getSourceControlBaseRevision();
    if (strlen($base)) {
      $refs[] = array(
        'type' => 'base',
        'identifier' => $base,
      );
    }

    $bookmark = $this->getBookmark();
    if (strlen($bookmark)) {
      $refs[] = array(
        'type' => 'bookmark',
        'name' => $bookmark,
      );
    }

    $revision_phid = null;
    if ($this->getRevisionID()) {
      $revision_phid = $this->getRevision()->getPHID();
    }

    return array(
      'revisionPHID' => $revision_phid,
      'authorPHID' => $this->getAuthorPHID(),
      'repositoryPHID' => $this->getRepositoryPHID(),
      'refs' => $refs,
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new DifferentialCommitsSearchEngineAttachment())
        ->setAttachmentKey('commits'),
    );
  }

}
