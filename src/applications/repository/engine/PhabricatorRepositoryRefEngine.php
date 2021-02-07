<?php

/**
 * Update the ref cursors for a repository, which track the positions of
 * branches, bookmarks, and tags.
 */
final class PhabricatorRepositoryRefEngine
  extends PhabricatorRepositoryEngine {

  private $newPositions = array();
  private $deadPositions = array();
  private $permanentCommits = array();
  private $rebuild;

  public function setRebuild($rebuild) {
    $this->rebuild = $rebuild;
    return $this;
  }

  public function getRebuild() {
    return $this->rebuild;
  }

  public function updateRefs() {
    $this->newPositions = array();
    $this->deadPositions = array();
    $this->permanentCommits = array();

    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $branches_may_close = false;

    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // No meaningful refs of any type in Subversion.
        $maps = array();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $branches = $this->loadMercurialBranchPositions($repository);
        $bookmarks = $this->loadMercurialBookmarkPositions($repository);
        $maps = array(
          PhabricatorRepositoryRefCursor::TYPE_BRANCH => $branches,
          PhabricatorRepositoryRefCursor::TYPE_BOOKMARK => $bookmarks,
        );

        $branches_may_close = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $maps = $this->loadGitRefPositions($repository);
        break;
      default:
        throw new Exception(pht('Unknown VCS "%s"!', $vcs));
    }

    // Fill in any missing types with empty lists.
    $maps = $maps + array(
      PhabricatorRepositoryRefCursor::TYPE_BRANCH => array(),
      PhabricatorRepositoryRefCursor::TYPE_TAG => array(),
      PhabricatorRepositoryRefCursor::TYPE_BOOKMARK => array(),
      PhabricatorRepositoryRefCursor::TYPE_REF => array(),
    );

    $all_cursors = id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->needPositions(true)
      ->execute();
    $cursor_groups = mgroup($all_cursors, 'getRefType');

    // Find all the heads of permanent refs.
    $all_closing_heads = array();
    foreach ($all_cursors as $cursor) {

      // See T13284. Note that we're considering whether this ref was a
      // permanent ref or not the last time we updated refs for this
      // repository. This allows us to handle things properly when a ref
      // is reconfigured from non-permanent to permanent.

      $was_permanent = $cursor->getIsPermanent();
      if (!$was_permanent) {
        continue;
      }

      foreach ($cursor->getPositionIdentifiers() as $identifier) {
        $all_closing_heads[] = $identifier;
      }
    }

    $all_closing_heads = array_unique($all_closing_heads);
    $all_closing_heads = $this->removeMissingCommits($all_closing_heads);

    foreach ($maps as $type => $refs) {
      $cursor_group = idx($cursor_groups, $type, array());
      $this->updateCursors($cursor_group, $refs, $type, $all_closing_heads);
    }

    if ($this->permanentCommits) {
      $this->setPermanentFlagOnCommits($this->permanentCommits);
    }

    $save_cursors = $this->getCursorsForUpdate($repository, $all_cursors);

    if ($this->newPositions || $this->deadPositions || $save_cursors) {
      $repository->openTransaction();

        $this->saveNewPositions();
        $this->deleteDeadPositions();

        foreach ($save_cursors as $cursor) {
          $cursor->save();
        }

      $repository->saveTransaction();
    }

    $branches = $maps[PhabricatorRepositoryRefCursor::TYPE_BRANCH];
    if ($branches && $branches_may_close) {
      $this->updateBranchStates($repository, $branches);
    }
  }

  private function getCursorsForUpdate(
    PhabricatorRepository $repository,
    array $cursors) {
    assert_instances_of($cursors, 'PhabricatorRepositoryRefCursor');

    $publisher = $repository->newPublisher();

    $results = array();

    foreach ($cursors as $cursor) {
      $diffusion_ref = $cursor->newDiffusionRepositoryRef();

      $is_permanent = $publisher->isPermanentRef($diffusion_ref);
      if ($is_permanent == $cursor->getIsPermanent()) {
        continue;
      }

      $cursor->setIsPermanent((int)$is_permanent);
      $results[] = $cursor;
    }

    return $results;
  }

  private function updateBranchStates(
    PhabricatorRepository $repository,
    array $branches) {

    assert_instances_of($branches, 'DiffusionRepositoryRef');
    $viewer = $this->getViewer();

    $all_cursors = id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->needPositions(true)
      ->execute();

    $state_map = array();
    $type_branch = PhabricatorRepositoryRefCursor::TYPE_BRANCH;
    foreach ($all_cursors as $cursor) {
      if ($cursor->getRefType() !== $type_branch) {
        continue;
      }
      $raw_name = $cursor->getRefNameRaw();

      foreach ($cursor->getPositions() as $position) {
        $hash = $position->getCommitIdentifier();
        $state_map[$raw_name][$hash] = $position;
      }
    }

    $updates = array();
    foreach ($branches as $branch) {
      $position = idx($state_map, $branch->getShortName(), array());
      $position = idx($position, $branch->getCommitIdentifier());
      if (!$position) {
        continue;
      }

      $fields = $branch->getRawFields();

      $position_state = (bool)$position->getIsClosed();
      $branch_state = (bool)idx($fields, 'closed');

      if ($position_state != $branch_state) {
        $updates[$position->getID()] = (int)$branch_state;
      }
    }

    if ($updates) {
      $position_table = id(new PhabricatorRepositoryRefPosition());
      $conn = $position_table->establishConnection('w');

      $position_table->openTransaction();
        foreach ($updates as $position_id => $branch_state) {
          queryfx(
            $conn,
            'UPDATE %T SET isClosed = %d WHERE id = %d',
            $position_table->getTableName(),
            $branch_state,
            $position_id);
        }
      $position_table->saveTransaction();
    }
  }

  private function markPositionNew(
    PhabricatorRepositoryRefPosition $position) {
    $this->newPositions[] = $position;
    return $this;
  }

  private function markPositionDead(
    PhabricatorRepositoryRefPosition $position) {
    $this->deadPositions[] = $position;
    return $this;
  }

  private function markPermanentCommits(array $identifiers) {
    foreach ($identifiers as $identifier) {
      $this->permanentCommits[$identifier] = $identifier;
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
    $publisher = $repository->newPublisher();

    // NOTE: Mercurial branches may have multiple branch heads; this logic
    // is complex primarily to account for that.

    $cursors = mpull($cursors, null, 'getRefNameRaw');

    // Group all the new ref values by their name. As above, these groups may
    // have multiple members in Mercurial.
    $ref_groups = mgroup($new_refs, 'getShortName');

    foreach ($ref_groups as $name => $refs) {
      $new_commits = mpull($refs, 'getCommitIdentifier', 'getCommitIdentifier');

      $ref_cursor = idx($cursors, $name);
      if ($ref_cursor) {
        $old_positions = $ref_cursor->getPositions();
      } else {
        $old_positions = array();
      }

      // We're going to delete all the cursors pointing at commits which are
      // no longer associated with the refs. This primarily makes the Mercurial
      // multiple head case easier, and means that when we update a ref we
      // delete the old one and write a new one.
      foreach ($old_positions as $old_position) {
        $hash = $old_position->getCommitIdentifier();
        if (isset($new_commits[$hash])) {
          // This ref previously pointed at this commit, and still does.
          $this->log(
            pht(
              'Ref %s "%s" still points at %s.',
              $ref_type,
              $name,
              $hash));
          continue;
        }

        // This ref previously pointed at this commit, but no longer does.
        $this->log(
          pht(
            'Ref %s "%s" no longer points at %s.',
            $ref_type,
            $name,
            $hash));

        // Nuke the obsolete cursor.
        $this->markPositionDead($old_position);
      }

      // Now, we're going to insert new cursors for all the commits which are
      // associated with this ref that don't currently have cursors.
      $old_commits = mpull($old_positions, 'getCommitIdentifier');
      $old_commits = array_fuse($old_commits);

      $added_commits = array_diff_key($new_commits, $old_commits);
      foreach ($added_commits as $identifier) {
        $this->log(
          pht(
            'Ref %s "%s" now points at %s.',
            $ref_type,
            $name,
            $identifier));

        if (!$ref_cursor) {
          // If this is the first time we've seen a particular ref (for
          // example, a new branch) we need to insert a RefCursor record
          // for it before we can insert a RefPosition.

          $ref_cursor = $this->newRefCursor(
            $repository,
            $ref_type,
            $name);
        }

        $new_position = id(new PhabricatorRepositoryRefPosition())
          ->setCursorID($ref_cursor->getID())
          ->setCommitIdentifier($identifier)
          ->setIsClosed(0);

        $this->markPositionNew($new_position);
      }

      if ($publisher->isPermanentRef(head($refs))) {

        // See T13284. If this cursor was already marked as permanent, we
        // only need to publish the newly created ref positions. However, if
        // this cursor was not previously permanent but has become permanent,
        // we need to publish all the ref positions.

        // This corresponds to users reconfiguring a branch to make it
        // permanent without pushing any new commits to it.

        $is_rebuild = $this->getRebuild();
        $was_permanent = $ref_cursor->getIsPermanent();

        if ($is_rebuild || !$was_permanent) {
          $update_all = true;
        } else {
          $update_all = false;
        }

        if ($update_all) {
          $update_commits = $new_commits;
        } else {
          $update_commits = $added_commits;
        }

        if ($is_rebuild) {
          $exclude = array();
        } else {
          $exclude = $all_closing_heads;
        }

        foreach ($update_commits as $identifier) {
          $new_identifiers = $this->loadNewCommitIdentifiers(
            $identifier,
            $exclude);

          $this->markPermanentCommits($new_identifiers);
        }
      }
    }

    // Find any cursors for refs which no longer exist. This happens when a
    // branch, tag or bookmark is deleted.

    foreach ($cursors as $name => $cursor) {
      if (!empty($ref_groups[$name])) {
        // This ref still has some positions, so we don't need to wipe it
        // out. Try the next one.
        continue;
      }

      foreach ($cursor->getPositions() as $position) {
        $this->log(
          pht(
            'Ref %s "%s" no longer exists.',
            $cursor->getRefType(),
            $cursor->getRefName()));

        $this->markPositionDead($position);
      }
    }
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

          // See PHI1474. This length of list may exceed the maximum size of
          // a command line argument list, so pipe the list in using "--stdin"
          // instead.

          $ref_list = array();
          $ref_list[] = $new_head;
          foreach ($all_closing_heads as $old_head) {
            $ref_list[] = '^'.$old_head;
          }
          $ref_list[] = '--';
          $ref_list = implode("\n", $ref_list)."\n";

          $future = $this->getRepository()->getLocalCommandFuture(
            'log %s --stdin --',
            '--format=%H');

          list($stdout) = $future
            ->write($ref_list)
            ->resolvex();
        } else {
          list($stdout) = $this->getRepository()->execxLocalCommand(
            'log %s %s --',
            '--format=%H',
            gitsprintf('%s', $new_head));
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
   * Mark a list of commits as permanent, and queue workers for those commits
   * which don't already have the flag.
   */
  private function setPermanentFlagOnCommits(array $identifiers) {
    $repository = $this->getRepository();
    $commit_table = new PhabricatorRepositoryCommit();
    $conn = $commit_table->establishConnection('w');

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
        throw new Exception(pht("Unknown repository type '%s'!", $vcs));
    }

    $identifier_tokens = array();
    foreach ($identifiers as $identifier) {
      $identifier_tokens[] = qsprintf(
        $conn,
        '%s',
        $identifier);
    }

    $all_commits = array();
    foreach (PhabricatorLiskDAO::chunkSQL($identifier_tokens) as $chunk) {
      $rows = queryfx_all(
        $conn,
        'SELECT id, phid, commitIdentifier, importStatus FROM %T
          WHERE repositoryID = %d AND commitIdentifier IN (%LQ)',
        $commit_table->getTableName(),
        $repository->getID(),
        $chunk);
      foreach ($rows as $row) {
        $all_commits[] = $row;
      }
    }

    $commit_refs = array();
    foreach ($identifiers as $identifier) {

      // See T13591. This construction is a bit ad-hoc, but the priority
      // function currently only cares about the number of refs we have
      // discovered, so we'll get the right result even without filling
      // these records out in detail.

      $commit_refs[] = id(new PhabricatorRepositoryCommitRef())
        ->setIdentifier($identifier);
    }

    $task_priority = $this->getImportTaskPriority(
      $repository,
      $commit_refs);

    $permanent_flag = PhabricatorRepositoryCommit::IMPORTED_PERMANENT;
    $published_flag = PhabricatorRepositoryCommit::IMPORTED_PUBLISH;

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

      $import_status = $row['importStatus'];
      if (!($import_status & $permanent_flag)) {
        // Set the "permanent" flag.
        $import_status = ($import_status | $permanent_flag);

        // See T13580. Clear the "published" flag, so publishing executes
        // again. We may have previously performed a no-op "publish" on the
        // commit to make sure it has all bits in the "IMPORTED_ALL" bitmask.
        $import_status = ($import_status & ~$published_flag);

        queryfx(
          $conn,
          'UPDATE %T SET importStatus = %d WHERE id = %d',
          $commit_table->getTableName(),
          $import_status,
          $row['id']);

        $this->queueCommitImportTask(
          $repository,
          $row['phid'],
          $task_priority,
          $via = 'ref');
      }
    }

    return $this;
  }

  private function newRefCursor(
    PhabricatorRepository $repository,
    $ref_type,
    $ref_name) {

    $cursor = id(new PhabricatorRepositoryRefCursor())
      ->setRepositoryPHID($repository->getPHID())
      ->setRefType($ref_type)
      ->setRefName($ref_name);

    $publisher = $repository->newPublisher();

    $diffusion_ref = $cursor->newDiffusionRepositoryRef();
    $is_permanent = $publisher->isPermanentRef($diffusion_ref);

    $cursor->setIsPermanent((int)$is_permanent);

    try {
      return $cursor->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // If we raced another daemon to create this position and lost the race,
      // load the cursor the other daemon created instead.
    }

    $viewer = $this->getViewer();

    $cursor = id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->withRefTypes(array($ref_type))
      ->withRefNames(array($ref_name))
      ->needPositions(true)
      ->executeOne();
    if (!$cursor) {
      throw new Exception(
        pht(
          'Failed to create a new ref cursor (for "%s", of type "%s", in '.
          'repository "%s") because it collided with an existing cursor, '.
          'but then failed to load that cursor.',
          $ref_name,
          $ref_type,
          $repository->getDisplayName()));
    }

    return $cursor;
  }

  private function saveNewPositions() {
    $positions = $this->newPositions;

    foreach ($positions as $position) {
      try {
        $position->save();
      } catch (AphrontDuplicateKeyQueryException $ex) {
        // We may race another daemon to create this position. If we do, and
        // we lose the race, that's fine: the other daemon did our work for
        // us and we can continue.
      }
    }

    $this->newPositions = array();
  }

  private function deleteDeadPositions() {
    $positions = $this->deadPositions;
    $repository = $this->getRepository();

    foreach ($positions as $position) {
      // Shove this ref into the old refs table so the discovery engine
      // can check if any commits have been rendered unreachable.
      id(new PhabricatorRepositoryOldRef())
        ->setRepositoryPHID($repository->getPHID())
        ->setCommitIdentifier($position->getCommitIdentifier())
        ->save();

      $position->delete();
    }

    $this->deadPositions = array();
  }



/* -(  Updating Git Refs  )-------------------------------------------------- */


  /**
   * @task git
   */
  private function loadGitRefPositions(PhabricatorRepository $repository) {
    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->execute();

    return mgroup($refs, 'getRefType');
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
