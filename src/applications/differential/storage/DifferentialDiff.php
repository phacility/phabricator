<?php

final class DifferentialDiff
  extends DifferentialDAO
  implements
    PhabricatorPolicyInterface,
    HarbormasterBuildableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $revisionID;
  protected $authorPHID;
  protected $repositoryPHID;

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

  protected $arcanistProjectPHID;
  protected $creationMethod;
  protected $repositoryUUID;

  protected $description;

  protected $viewPolicy;

  private $unsavedChangesets = array();
  private $changesets = self::ATTACHABLE;
  private $revision = self::ATTACHABLE;
  private $properties = array();
  private $buildable = self::ATTACHABLE;

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
        'arcanistProjectPHID' => 'phid?',
        'repositoryUUID' => 'text64?',

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
    return id(new DifferentialChangeset())->loadAllWhere(
      'diffID = %d',
      $this->getID());
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
          $dhunk = new DifferentialModernHunk();
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

    $parser = new DifferentialChangesetParser();
    $changesets = $parser->detectCopiedCode(
      $diff->getChangesets(),
      $min_width = 30,
      $min_lines = 3);
    $diff->attachChangesets($changesets);

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
      'properties' => array(),
    );

    $dict['changes'] = $this->buildChangesList();

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

  public function attachBuildable(HarbormasterBuildable $buildable = null) {
    $this->buildable = $buildable;
    return $this;
  }

  public function getBuildable() {
    return $this->assertAttached($this->buildable);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    if ($this->hasRevision()) {
      return $this->getRevision()->getPolicy($capability);
    }

    return $this->viewPolicy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->hasRevision()) {
      return $this->getRevision()->hasAutomaticCapability($capability, $viewer);
    }

    return ($this->getAuthorPHID() == $viewer->getPhid());
  }

  public function describeAutomaticCapability($capability) {
    if ($this->hasRevision()) {
      return pht(
        'This diff is attached to a revision, and inherits its policies.');
    }
    return pht('The author of a diff can see it.');
  }



/* -(  HarbormasterBuildableInterface  )------------------------------------- */


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
        $results['repository.vcs'] = $repo->getVersionControlSystem();
        $results['repository.uri'] = $repo->getPublicCloneURI();
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
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
    );
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DifferentialDiffEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new DifferentialDiffTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      foreach ($this->loadChangesets() as $changeset) {
        $changeset->delete();
      }

      $properties = id(new DifferentialDiffProperty())->loadAllWhere(
        'diffID = %d',
        $this->getID());
      foreach ($properties as $prop) {
        $prop->delete();
      }

    $this->saveTransaction();
  }

}
