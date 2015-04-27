<?php

/**
 * Update the ref cursors for a repository, which track the positions of
 * branches, bookmarks, and tags.
 */
final class PhabricatorRepositoryRefEngine
  extends PhabricatorRepositoryEngine {

  private $newRefs = array();
  private $deadRefs = array();
  private $closeCommits = array();
  private $hasNoCursors;

  public function updateRefs() {
    $this->newRefs = array();
    $this->deadRefs = array();
    $this->closeCommits = array();

    $repository = $this->getRepository();

    $branches_may_close = false;

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
        $branches_may_close = true;
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

    $this->hasNoCursors = (!$all_cursors);

    // Find all the heads of closing refs.
    $all_closing_heads = array();
    foreach ($all_cursors as $cursor) {
      if ($this->shouldCloseRef($cursor->getRefType(), $cursor->getRefName())) {
        $all_closing_heads[] = $cursor->getCommitIdentifier();
      }
    }
    $all_closing_heads = array_unique($all_closing_heads);
    $all_closing_heads = $this->removeMissingCommits($all_closing_heads);

    foreach ($maps as $type => $refs) {
      $cursor_group = idx($cursor_groups, $type, array());
      $this->updateCursors($cursor_group, $refs, $type, $all_closing_heads);
    }

    if ($this->closeCommits) {
      $this->setCloseFlagOnCommits($this->closeCommits);
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

    if ($branches && $branches_may_close) {
      $this->updateBranchStates($repository, $branches);
    }
  }

  private function updateBranchStates(
    PhabricatorRepository $repository,
    array $branches) {

    assert_instances_of($branches, 'DiffusionRepositoryRef');

    $all_cursors = id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();

    $state_map = array();
    $type_branch = PhabricatorRepositoryRefCursor::TYPE_BRANCH;
    foreach ($all_cursors as $cursor) {
      if ($cursor->getRefType() !== $type_branch) {
        continue;
      }
      $raw_name = $cursor->getRefNameRaw();
      $hash = $cursor->getCommitIdentifier();

      $state_map[$raw_name][$hash] = $cursor;
    }

    foreach ($branches as $branch) {
      $cursor = idx($state_map, $branch->getShortName(), array());
      $cursor = idx($cursor, $branch->getCommitIdentifier());
      if (!$cursor) {
        continue;
      }

      $fields = $branch->getRawFields();

      $cursor_state = (bool)$cursor->getIsClosed();
      $branch_state = (bool)idx($fields, 'closed');

      if ($cursor_state != $branch_state) {
        $cursor->setIsClosed((int)$branch_state)->save();
      }
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

  private function markCloseCommits(array $identifiers) {
    foreach ($identifiers as $identifier) {
      $this->closeCommits[$identifier] = $identifier;
    }
    return $this;
  }

  /**
   * Remove commits which no longer exist in the repository from a list.
   *
   * After a force push and garbage collection, we may have branch cursors which
   * point at commits which no longer exist. This can make commands issued later
   * fail. See T5839 for discussion.
   *
   * @param list<string>    List of commit identifiers.
   * @return list<string>   List with nonexistent identifiers removed.
   */
  private function removeMissingCommits(array $identifiers) {
    if (!$identifiers) {
      return array();
    }

    $resolved = id(new DiffusionLowLevelResolveRefsQuery())
      ->setRepository($this->getRepository())
      ->withRefs($identifiers)
      ->execute();

    foreach ($identifiers as $key => $identifier) {
      if (empty($resolved[$identifier])) {
        unset($identifiers[$key]);
      }
    }

    return $identifiers;
  }

  private function updateCursors(
    array $cursors,
    array $new_refs,
    $ref_type,
    array $all_closing_heads) {
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

      if ($this->shouldCloseRef($ref_type, $name)) {
        foreach ($added_commits as $identifier) {
          $new_identifiers = $this->loadNewCommitIdentifiers(
            $identifier,
            $all_closing_heads);

          $this->markCloseCommits($new_identifiers);
        }
      }
    }

    // Find any cursors for refs which no longer exist. This happens when a
    // branch, tag or bookmark is deleted.

    foreach ($cursor_groups as $name => $cursor_group) {
      if (idx($ref_groups, $name) === null) {
        foreach ($cursor_group as $cursor) {
          $this->log(
            pht(
              'Ref %s "%s" no longer exists.',
              $cursor->getRefType(),
              $cursor->getRefName()));
          $this->markRefDead($cursor);
        }
      }
    }
  }

  private function shouldCloseRef($ref_type, $ref_name) {
    if ($ref_type !== PhabricatorRepositoryRefCursor::TYPE_BRANCH) {
      return false;
    }

    if ($this->hasNoCursors) {
      // If we don't have any cursors, don't close things. Particularly, this
      // corresponds to the case where you've just updated to this code on an
      // existing repository: we don't want to requeue message steps for every
      // commit on a closeable ref.
      return false;
    }

    return $this->getRepository()->shouldAutocloseBranch($ref_name);
  }

  /**
   * Find all ancestors of a new closing branch head which are not ancestors
   * of any old closing branch head.
   */
  private function loadNewCommitIdentifiers(
    $new_head,
    array $all_closing_heads) {

    $repository = $this->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        if ($all_closing_heads) {
          $parts = array();
          foreach ($all_closing_heads as $head) {
            $parts[] = hgsprintf('%s', $head);
          }

          // See T5896. Mercurial can not parse an "X or Y or ..." rev list
          // with more than about 300 items, because it exceeds the maximum
          // allowed recursion depth. Split all the heads into chunks of
          // 256, and build a query like this:
          //
          //   ((1 or 2 or ... or 255) or (256 or 257 or ... 511))
          //
          // If we have more than 65535 heads, we'll do that again:
          //
          //   (((1 or ...) or ...) or ((65536 or ...) or ...))

          $chunk_size = 256;
          while (count($parts) > $chunk_size) {
            $chunks = array_chunk($parts, $chunk_size);
            foreach ($chunks as $key => $chunk) {
              $chunks[$key] = '('.implode(' or ', $chunk).')';
            }
            $parts = array_values($chunks);
          }
          $parts = '('.implode(' or ', $parts).')';

          list($stdout) = $this->getRepository()->execxLocalCommand(
            'log --template %s --rev %s',
            '{node}\n',
            hgsprintf('%s', $new_head).' - '.$parts);
        } else {
          list($stdout) = $this->getRepository()->execxLocalCommand(
            'log --template %s --rev %s',
            '{node}\n',
            hgsprintf('%s', $new_head));
        }

        $stdout = trim($stdout);
        if (!strlen($stdout)) {
          return array();
        }
        return phutil_split_lines($stdout, $retain_newlines = false);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        if ($all_closing_heads) {
          list($stdout) = $this->getRepository()->execxLocalCommand(
            'log --format=%s %s --not %Ls',
            '%H',
            $new_head,
            $all_closing_heads);
        } else {
          list($stdout) = $this->getRepository()->execxLocalCommand(
            'log --format=%s %s',
            '%H',
            $new_head);
        }

        $stdout = trim($stdout);
        if (!strlen($stdout)) {
          return array();
        }
        return phutil_split_lines($stdout, $retain_newlines = false);
      default:
        throw new Exception(pht('Unsupported VCS "%s"!', $vcs));
    }
  }

  /**
   * Mark a list of commits as closeable, and queue workers for those commits
   * which don't already have the flag.
   */
  private function setCloseFlagOnCommits(array $identifiers) {
    $repository = $this->getRepository();
    $commit_table = new PhabricatorRepositoryCommit();
    $conn_w = $commit_table->establishConnection('w');

    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $class = 'PhabricatorRepositoryGitCommitMessageParserWorker';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $class = 'PhabricatorRepositorySvnCommitMessageParserWorker';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $class = 'PhabricatorRepositoryMercurialCommitMessageParserWorker';
        break;
      default:
        throw new Exception("Unknown repository type '{$vcs}'!");
    }

    $all_commits = queryfx_all(
      $conn_w,
      'SELECT id, commitIdentifier, importStatus FROM %T
        WHERE repositoryID = %d AND commitIdentifier IN (%Ls)',
      $commit_table->getTableName(),
      $repository->getID(),
      $identifiers);

    $closeable_flag = PhabricatorRepositoryCommit::IMPORTED_CLOSEABLE;

    $all_commits = ipull($all_commits, null, 'commitIdentifier');
    foreach ($identifiers as $identifier) {
      $row = idx($all_commits, $identifier);

      if (!$row) {
        throw new Exception(
          pht(
            'Commit "%s" has not been discovered yet! Run discovery before '.
            'updating refs.',
            $identifier));
      }

      if (!($row['importStatus'] & $closeable_flag)) {
        queryfx(
          $conn_w,
          'UPDATE %T SET importStatus = (importStatus | %d) WHERE id = %d',
          $commit_table->getTableName(),
          $closeable_flag,
          $row['id']);

        $data = array(
          'commitID' => $row['id'],
          'only' => true,
        );

        PhabricatorWorker::scheduleTask($class, $data);
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
