<?php

final class HeraldPreCommitContentAdapter extends HeraldPreCommitAdapter {

  private $changesets;
  private $commitRef;
  private $fields;
  private $revision = false;

  public function getAdapterContentName() {
    return pht('Commit Hook: Commit Content');
  }

  public function getAdapterSortOrder() {
    return 2500;
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to commits being pushed to hosted repositories.\n".
      "Hook rules can block changes and send push summary mail.");
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_AUTHOR_RAW,
        self::FIELD_COMMITTER,
        self::FIELD_COMMITTER_RAW,
        self::FIELD_BRANCHES,
        self::FIELD_DIFF_FILE,
        self::FIELD_DIFF_CONTENT,
        self::FIELD_DIFF_ADDED_CONTENT,
        self::FIELD_DIFF_REMOVED_CONTENT,
        self::FIELD_DIFF_ENORMOUS,
        self::FIELD_REPOSITORY,
        self::FIELD_REPOSITORY_PROJECTS,
        self::FIELD_PUSHER,
        self::FIELD_PUSHER_PROJECTS,
        self::FIELD_PUSHER_IS_COMMITTER,
        self::FIELD_DIFFERENTIAL_REVISION,
        self::FIELD_DIFFERENTIAL_ACCEPTED,
        self::FIELD_DIFFERENTIAL_REVIEWERS,
        self::FIELD_DIFFERENTIAL_CCS,
        self::FIELD_IS_MERGE_COMMIT,
      ),
      parent::getFields());
  }

  public function getHeraldName() {
    return pht('Push Log (Content)');
  }

  public function getHeraldField($field) {
    $log = $this->getObject();
    switch ($field) {
      case self::FIELD_BODY:
        return $this->getCommitRef()->getMessage();
      case self::FIELD_AUTHOR:
        return $this->getAuthorPHID();
      case self::FIELD_AUTHOR_RAW:
        return $this->getAuthorRaw();
      case self::FIELD_COMMITTER:
        return $this->getCommitterPHID();
      case self::FIELD_COMMITTER_RAW:
        return $this->getCommitterRaw();
      case self::FIELD_BRANCHES:
        return $this->getBranches();
      case self::FIELD_DIFF_FILE:
        return $this->getDiffContent('name');
      case self::FIELD_DIFF_CONTENT:
        return $this->getDiffContent('*');
      case self::FIELD_DIFF_ADDED_CONTENT:
        return $this->getDiffContent('+');
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return $this->getDiffContent('-');
      case self::FIELD_DIFF_ENORMOUS:
        $this->getDiffContent('*');
        return ($this->changesets instanceof Exception);
      case self::FIELD_REPOSITORY:
        return $this->getHookEngine()->getRepository()->getPHID();
      case self::FIELD_REPOSITORY_PROJECTS:
        return $this->getHookEngine()->getRepository()->getProjectPHIDs();
      case self::FIELD_PUSHER:
        return $this->getHookEngine()->getViewer()->getPHID();
      case self::FIELD_PUSHER_PROJECTS:
        return $this->getHookEngine()->loadViewerProjectPHIDsForHerald();
      case self::FIELD_DIFFERENTIAL_REVISION:
        $revision = $this->getRevision();
        if (!$revision) {
          return null;
        }
        return $revision->getPHID();
      case self::FIELD_DIFFERENTIAL_ACCEPTED:
        $revision = $this->getRevision();
        if (!$revision) {
          return null;
        }
        $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
        if ($revision->getStatus() != $status_accepted) {
          return null;
        }
        return $revision->getPHID();
      case self::FIELD_DIFFERENTIAL_REVIEWERS:
        $revision = $this->getRevision();
        if (!$revision) {
          return array();
        }
        return $revision->getReviewers();
      case self::FIELD_DIFFERENTIAL_CCS:
        $revision = $this->getRevision();
        if (!$revision) {
          return array();
        }
        return $revision->getCCPHIDs();
      case self::FIELD_IS_MERGE_COMMIT:
        return $this->getIsMergeCommit();
      case self::FIELD_PUSHER_IS_COMMITTER:
        $pusher_phid = $this->getHookEngine()->getViewer()->getPHID();
        return ($this->getCommitterPHID() == $pusher_phid);
    }

    return parent::getHeraldField($field);
  }

  private function getDiffContent($type) {
    if ($this->changesets === null) {
      try {
        $this->changesets = $this->getHookEngine()->loadChangesetsForCommit(
          $this->getObject()->getRefNew());
      } catch (Exception $ex) {
        $this->changesets = $ex;
      }
    }

    if ($this->changesets instanceof Exception) {
      $ex_class = get_class($this->changesets);
      $ex_message = $this->changesets->getmessage();
      if ($type === 'name') {
        return array("<{$ex_class}: {$ex_message}>");
      } else {
        return array("<{$ex_class}>" => $ex_message);
      }
    }

    $result = array();
    if ($type === 'name') {
      foreach ($this->changesets as $change) {
        $result[] = $change->getFilename();
      }
    } else {
      foreach ($this->changesets as $change) {
        $lines = array();
        foreach ($change->getHunks() as $hunk) {
          switch ($type) {
            case '-':
              $lines[] = $hunk->makeOldFile();
              break;
            case '+':
              $lines[] = $hunk->makeNewFile();
              break;
            case '*':
            default:
              $lines[] = $hunk->makeChanges();
              break;
          }
        }
        $result[$change->getFilename()] = implode('', $lines);
      }
    }

    return $result;
  }

  private function getCommitRef() {
    if ($this->commitRef === null) {
      $this->commitRef = $this->getHookEngine()->loadCommitRefForCommit(
        $this->getObject()->getRefNew());
    }
    return $this->commitRef;
  }

  private function getAuthorPHID() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $ref = $this->getCommitRef();
        $author = $ref->getAuthor();
        if (!strlen($author)) {
          return null;
        }
        return $this->lookupUser($author);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the author.
        return $this->getHookEngine()->getViewer()->getPHID();
    }
  }

  private function getCommitterPHID() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // If there's no committer information, we're going to return the
        // author instead. However, if there's committer information and we
        // can't resolve it, return `null`.
        $ref = $this->getCommitRef();
        $committer = $ref->getCommitter();
        if (!strlen($committer)) {
          return $this->getAuthorPHID();
        }
        return $this->lookupUser($committer);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the committer.
        return $this->getHookEngine()->getViewer()->getPHID();
    }
  }

  private function getAuthorRaw() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $ref = $this->getCommitRef();
        return $ref->getAuthor();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the author.
        return $this->getHookEngine()->getViewer()->getUsername();
    }
  }

  private function getCommitterRaw() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // Here, if there's no committer, we're going to return the author
        // instead.
        $ref = $this->getCommitRef();
        $committer = $ref->getCommitter();
        if (strlen($committer)) {
          return $committer;
        }
        return $ref->getAuthor();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the committer.
        return $this->getHookEngine()->getViewer()->getUsername();
    }
  }

  private function lookupUser($author) {
    return id(new DiffusionResolveUserQuery())
      ->withName($author)
      ->execute();
  }

  private function getCommitFields() {
    if ($this->fields === null) {
      $this->fields = id(new DiffusionLowLevelCommitFieldsQuery())
        ->setRepository($this->getHookEngine()->getRepository())
        ->withCommitRef($this->getCommitRef())
        ->execute();
    }
    return $this->fields;
  }

  private function getRevision() {
    if ($this->revision === false) {
      $fields = $this->getCommitFields();
      $revision_id = idx($fields, 'revisionID');
      if (!$revision_id) {
        $this->revision = null;
      } else {
        $this->revision = id(new DifferentialRevisionQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withIDs(array($revision_id))
          ->needRelationships(true)
          ->executeOne();
      }
    }

    return $this->revision;
  }

  private function getIsMergeCommit() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $parents = id(new DiffusionLowLevelParentsQuery())
          ->setRepository($repository)
          ->withIdentifier($this->getObject()->getRefNew())
          ->execute();

        return (count($parents) > 1);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // NOTE: For now, we ignore "svn:mergeinfo" at all levels. We might
        // change this some day, but it's not nearly as clear a signal as
        // ancestry is in Git/Mercurial.
        return false;
    }
  }

  private function getBranches() {
    return $this->getHookEngine()->loadBranches(
      $this->getObject()->getRefNew());
  }

}
