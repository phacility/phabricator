<?php

final class DifferentialDiff extends DifferentialDAO {

  protected $revisionID;
  protected $authorPHID;

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

  protected $parentRevisionID;
  protected $arcanistProjectPHID;
  protected $creationMethod;
  protected $repositoryUUID;

  protected $description;

  private $unsavedChangesets = array();
  private $changesets;

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
    if ($this->changesets === null) {
      throw new Exception("Must load and attach changesets first!");
    }
    return $this->changesets;
  }

  public function loadChangesets() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialChangeset())->loadAllWhere(
      'diffID = %d',
      $this->getID());
  }

  public function loadArcanistProject() {
    if (!$this->getArcanistProjectPHID()) {
      return null;
    }
    return id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
      'phid = %s',
      $this->getArcanistProjectPHID());
  }

  public function getBackingVersionControlSystem() {
    $arcanist_project = $this->loadArcanistProject();
    if (!$arcanist_project) {
      return null;
    }
    $repository = $arcanist_project->loadRepository();
    if (!$repository) {
      return null;
    }
    return $repository->getVersionControlSystem();
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

  public function delete() {
    $this->openTransaction();
      foreach ($this->loadChangesets() as $changeset) {
        $changeset->delete();
      }

      $properties = id(new DifferentialDiffProperty())->loadAllWhere(
        'diffID = %d',
        $this->getID());
      foreach ($properties as $prop) {
        $prop->delete();
      }

      $ret = parent::delete();
    $this->saveTransaction();
    return $ret;
  }

  public static function newFromRawChanges(array $changes) {
    assert_instances_of($changes, 'ArcanistDiffChange');
    $diff = new DifferentialDiff();

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
      'parent' => $this->getParentRevisionID(),
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
      $dict['changes'][] = $change;
    }

    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $this->getID());
    foreach ($properties as $property) {
      $dict['properties'][$property->getName()] = $property->getData();
    }

    return $dict;
  }

  /**
   * Figures out the right author information for a given diff based on the
   * repository and Phabricator configuration settings.
   *
   * Git is particularly finicky as it requires author information to be in
   * the format "George Washington <gwashington@example.com>" to
   * consistently work. If the Phabricator instance isn't configured to
   * expose emails prudently, then we are unable to get any author information
   * for git.
   */
  public function loadAuthorInformation() {
    $author = id(new PhabricatorUser())
      ->loadOneWhere('phid = %s', $this->getAuthorPHID());

    $use_emails =
      PhabricatorEnv::getEnvConfig('differential.expose-emails-prudently');

    switch ($this->getSourceControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        if (!$use_emails) {
          $author_info = '';
        } else {
          $author_info = $this->getFullAuthorInfo($author);
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        if (!$use_emails) {
          $author_info = $author->getUsername();
        } else {
          $author_info = $this->getFullAuthorInfo($author);
        }
       break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      default:
        $author_info = $author->getUsername();
        break;
    }

    return $author_info;
  }

  private function getFullAuthorInfo(PhabricatorUser $author) {
    return sprintf('%s <%s>',
                   $author->getRealName(),
                   $author->loadPrimaryEmailAddress());
  }
}
