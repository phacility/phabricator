<?php

/**
 * Update the ref cursors for a repository, which track the positions of
 * branches, bookmarks, and tags.
 */
final class PhabricatorRepositoryRefEngine
  extends PhabricatorRepositoryEngine {

  private $newRefs = array();
  private $deadRefs = array();

  public function updateRefs() {
    $this->newRefs = array();
    $this->deadRefs = array();

    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // No meaningful refs of any type in Subversion.
        $branches = array();
        $bookmarks = array();
        $tags = array();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $branches = $this->loadMercurialBranchPositions($repository);
        $bookmarks = $this->loadMercurialBookmarkPositions($repository);
        $tags = array();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $branches = $this->loadGitBranchPositions($repository);
        $bookmarks = array();
        $tags = $this->loadGitTagPositions($repository);
        break;
      default:
        throw new Exception(pht('Unknown VCS "%s"!', $vcs));
    }

    $maps = array(
      PhabricatorRepositoryRefCursor::TYPE_BRANCH => $branches,
      PhabricatorRepositoryRefCursor::TYPE_TAG => $tags,
      PhabricatorRepositoryRefCursor::TYPE_BOOKMARK => $bookmarks,
    );

    $all_cursors = id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();
    $cursor_groups = mgroup($all_cursors, 'getRefType');

    foreach ($maps as $type => $refs) {
      $cursor_group = idx($cursor_groups, $type, array());
      $this->updateCursors($cursor_group, $refs, $type);
    }

    if ($this->newRefs || $this->deadRefs) {
      $repository->openTransaction();
        foreach ($this->newRefs as $ref) {
          $ref->save();
        }
        foreach ($this->deadRefs as $ref) {
          $ref->delete();
        }
      $repository->saveTransaction();

      $this->newRefs = array();
      $this->deadRefs = array();
    }
  }

  private function markRefNew(PhabricatorRepositoryRefCursor $cursor) {
    $this->newRefs[] = $cursor;
    return $this;
  }

  private function markRefDead(PhabricatorRepositoryRefCursor $cursor) {
    $this->deadRefs[] = $cursor;
    return $this;
  }

  private function updateCursors(
    array $cursors,
    array $new_refs,
    $ref_type) {
    $repository = $this->getRepository();

    // NOTE: Mercurial branches may have multiple branch heads; this logic
    // is complex primarily to account for that.

    // Group all the cursors by their ref name, like "master". Since Mercurial
    // branches may have multiple heads, there could be several cursors with
    // the same name.
    $cursor_groups = mgroup($cursors, 'getRefNameRaw');

    // Group all the new ref values by their name. As above, these groups may
    // have multiple members in Mercurial.
    $ref_groups = mgroup($new_refs, 'getShortName');

    foreach ($ref_groups as $name => $refs) {
      $new_commits = mpull($refs, 'getCommitIdentifier', 'getCommitIdentifier');

      $ref_cursors = idx($cursor_groups, $name, array());
      $old_commits = mpull($ref_cursors, null, 'getCommitIdentifier');

      // We're going to delete all the cursors pointing at commits which are
      // no longer associated with the refs. This primarily makes the Mercurial
      // multiple head case easier, and means that when we update a ref we
      // delete the old one and write a new one.
      foreach ($ref_cursors as $cursor) {
        if (isset($new_commits[$cursor->getCommitIdentifier()])) {
          // This ref previously pointed at this commit, and still does.
          $this->log(
            pht(
              'Ref %s "%s" still points at %s.',
              $ref_type,
              $name,
              $cursor->getCommitIdentifier()));
        } else {
          // This ref previously pointed at this commit, but no longer does.
          $this->log(
            pht(
              'Ref %s "%s" no longer points at %s.',
              $ref_type,
              $name,
              $cursor->getCommitIdentifier()));

          // Nuke the obsolete cursor.
          $this->markRefDead($cursor);
        }
      }

      // Now, we're going to insert new cursors for all the commits which are
      // associated with this ref that don't currently have cursors.
      $added_commits = array_diff_key($new_commits, $old_commits);
      foreach ($added_commits as $identifier) {
        $this->log(
          pht(
            'Ref %s "%s" now points at %s.',
            $ref_type,
            $name,
            $identifier));
        $this->markRefNew(
          id(new PhabricatorRepositoryRefCursor())
            ->setRepositoryPHID($repository->getPHID())
            ->setRefType($ref_type)
            ->setRefName($name)
            ->setCommitIdentifier($identifier));
      }

      foreach ($added_commits as $identifier) {
        // TODO: Do autoclose stuff here.
      }
    }

    // Find any cursors for refs which no longer exist. This happens when a
    // branch, tag or bookmark is deleted.

    foreach ($cursor_groups as $name => $cursor_group) {
      if (idx($ref_groups, $name) === null) {
        $this->log(
          pht(
            'Ref %s "%s" no longer exists.',
            $cursor->getRefType(),
            $cursor->getRefName()));
        foreach ($cursor_group as $cursor) {
          $this->markRefDead($cursor);
        }
      }
    }
  }


/* -(  Updating Git Refs  )-------------------------------------------------- */


  /**
   * @task git
   */
  private function loadGitBranchPositions(PhabricatorRepository $repository) {
    return id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withIsOriginBranch(true)
      ->execute();
  }


  /**
   * @task git
   */
  private function loadGitTagPositions(PhabricatorRepository $repository) {
    return id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withIsTag(true)
      ->execute();
  }


/* -(  Updating Mercurial Refs  )-------------------------------------------- */


  /**
   * @task hg
   */
  private function loadMercurialBranchPositions(
    PhabricatorRepository $repository) {
    return id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository)
      ->execute();
  }


  /**
   * @task hg
   */
  private function loadMercurialBookmarkPositions(
    PhabricatorRepository $repository) {
    // TODO: Implement support for Mercurial bookmarks.
    return array();
  }

}
