<?php

/**
 * @task discover   Discovering Repositories
 * @task svn        Discovering Subversion Repositories
 * @task git        Discovering Git Repositories
 * @task hg         Discovering Mercurial Repositories
 * @task internal   Internals
 */
final class PhabricatorRepositoryDiscoveryEngine
  extends PhabricatorRepositoryEngine {

  private $repairMode;
  private $commitCache = array();
  private $workingSet = array();

  const MAX_COMMIT_CACHE_SIZE = 2048;


/* -(  Discovering Repositories  )------------------------------------------- */


  public function setRepairMode($repair_mode) {
    $this->repairMode = $repair_mode;
    return $this;
  }


  public function getRepairMode() {
    return $this->repairMode;
  }


  /**
   * @task discovery
   */
  public function discoverCommits() {
    $repository = $this->getRepository();

    $lock = $this->newRepositoryLock($repository, 'repo.look', false);

    try {
      $lock->lock();
    } catch (PhutilLockException $ex) {
      throw new DiffusionDaemonLockException(
        pht(
          'Another process is currently discovering repository "%s", '.
          'skipping discovery.',
          $repository->getDisplayName()));
    }

    try {
      $result = $this->discoverCommitsWithLock();
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();

    return $result;
  }

  private function discoverCommitsWithLock() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $refs = $this->discoverSubversionCommits();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $refs = $this->discoverMercurialCommits();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $refs = $this->discoverGitCommits();
        break;
      default:
        throw new Exception(pht("Unknown VCS '%s'!", $vcs));
    }

    if ($this->isInitialImport($refs)) {
      $this->log(
        pht(
          'Discovered more than %s commit(s) in an empty repository, '.
          'marking repository as importing.',
          new PhutilNumber(PhabricatorRepository::IMPORT_THRESHOLD)));

      $repository->markImporting();
    }

    // Clear the working set cache.
    $this->workingSet = array();

    // Record discovered commits and mark them in the cache.
    foreach ($refs as $ref) {
      $this->recordCommit(
        $repository,
        $ref->getIdentifier(),
        $ref->getEpoch(),
        $ref->getCanCloseImmediately(),
        $ref->getParents());

      $this->commitCache[$ref->getIdentifier()] = true;
    }

    $version = $this->getObservedVersion($repository);
    if ($version !== null) {
      id(new DiffusionRepositoryClusterEngine())
        ->setViewer($viewer)
        ->setRepository($repository)
        ->synchronizeWorkingCopyAfterDiscovery($version);
    }

    return $refs;
  }


/* -(  Discovering Git Repositories  )--------------------------------------- */


  /**
   * @task git
   */
  private function discoverGitCommits() {
    $repository = $this->getRepository();

    if (!$repository->isHosted()) {
      $this->verifyGitOrigin($repository);
    }

    // TODO: This should also import tags, but some of the logic is still
    // branch-specific today.

    $branches = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withRefTypes(
        array(
          PhabricatorRepositoryRefCursor::TYPE_BRANCH,
        ))
      ->execute();

    if (!$branches) {
      // This repository has no branches at all, so we don't need to do
      // anything. Generally, this means the repository is empty.
      return array();
    }

    $branches = $this->sortBranches($branches);
    $branches = mpull($branches, 'getCommitIdentifier', 'getShortName');

    $this->log(
      pht(
        'Discovering commits in repository "%s".',
        $repository->getDisplayName()));

    $this->fillCommitCache(array_values($branches));

    $refs = array();
    foreach ($branches as $name => $commit) {
      $this->log(pht('Examining branch "%s", at "%s".', $name, $commit));

      if (!$repository->shouldTrackBranch($name)) {
        $this->log(pht('Skipping, branch is untracked.'));
        continue;
      }

      if ($this->isKnownCommit($commit)) {
        $this->log(pht('Skipping, HEAD is known.'));
        continue;
      }

      $this->log(pht('Looking for new commits.'));

      $branch_refs = $this->discoverStreamAncestry(
        new PhabricatorGitGraphStream($repository, $commit),
        $commit,
        $repository->shouldAutocloseBranch($name));

      $this->didDiscoverRefs($branch_refs);

      $refs[] = $branch_refs;
    }

    return array_mergev($refs);
  }


/* -(  Discovering Subversion Repositories  )-------------------------------- */


  /**
   * @task svn
   */
  private function discoverSubversionCommits() {
    $repository = $this->getRepository();

    if (!$repository->isHosted()) {
      $this->verifySubversionRoot($repository);
    }

    $upper_bound = null;
    $limit = 1;
    $refs = array();
    do {
      // Find all the unknown commits on this path. Note that we permit
      // importing an SVN subdirectory rather than the entire repository, so
      // commits may be nonsequential.

      if ($upper_bound === null) {
        $at_rev = 'HEAD';
      } else {
        $at_rev = ($upper_bound - 1);
      }

      try {
        list($xml, $stderr) = $repository->execxRemoteCommand(
          'log --xml --quiet --limit %d %s',
          $limit,
          $repository->getSubversionBaseURI($at_rev));
      } catch (CommandException $ex) {
        $stderr = $ex->getStdErr();
        if (preg_match('/(path|File) not found/', $stderr)) {
          // We've gone all the way back through history and this path was not
          // affected by earlier commits.
          break;
        }
        throw $ex;
      }

      $xml = phutil_utf8ize($xml);
      $log = new SimpleXMLElement($xml);
      foreach ($log->logentry as $entry) {
        $identifier = (int)$entry['revision'];
        $epoch = (int)strtotime((string)$entry->date[0]);
        $refs[$identifier] = id(new PhabricatorRepositoryCommitRef())
          ->setIdentifier($identifier)
          ->setEpoch($epoch)
          ->setCanCloseImmediately(true);

        if ($upper_bound === null) {
          $upper_bound = $identifier;
        } else {
          $upper_bound = min($upper_bound, $identifier);
        }
      }

      // Discover 2, 4, 8, ... 256 logs at a time. This allows us to initially
      // import large repositories fairly quickly, while pulling only as much
      // data as we need in the common case (when we've already imported the
      // repository and are just grabbing one commit at a time).
      $limit = min($limit * 2, 256);

    } while ($upper_bound > 1 && !$this->isKnownCommit($upper_bound));

    krsort($refs);
    while ($refs && $this->isKnownCommit(last($refs)->getIdentifier())) {
      array_pop($refs);
    }
    $refs = array_reverse($refs);

    $this->didDiscoverRefs($refs);

    return $refs;
  }


  private function verifySubversionRoot(PhabricatorRepository $repository) {
    list($xml) = $repository->execxRemoteCommand(
      'info --xml %s',
      $repository->getSubversionPathURI());

    $xml = phutil_utf8ize($xml);
    $xml = new SimpleXMLElement($xml);

    $remote_root = (string)($xml->entry[0]->repository[0]->root[0]);
    $expect_root = $repository->getSubversionPathURI();

    $normal_type_svn = PhabricatorRepositoryURINormalizer::TYPE_SVN;

    $remote_normal = id(new PhabricatorRepositoryURINormalizer(
      $normal_type_svn,
      $remote_root))->getNormalizedPath();

    $expect_normal = id(new PhabricatorRepositoryURINormalizer(
      $normal_type_svn,
      $expect_root))->getNormalizedPath();

    if ($remote_normal != $expect_normal) {
      throw new Exception(
        pht(
          'Repository "%s" does not have a correctly configured remote URI. '.
          'The remote URI for a Subversion repository MUST point at the '.
          'repository root. The root for this repository is "%s", but the '.
          'configured URI is "%s". To resolve this error, set the remote URI '.
          'to point at the repository root. If you want to import only part '.
          'of a Subversion repository, use the "Import Only" option.',
          $repository->getDisplayName(),
          $remote_root,
          $expect_root));
    }
  }


/* -(  Discovering Mercurial Repositories  )--------------------------------- */


  /**
   * @task hg
   */
  private function discoverMercurialCommits() {
    $repository = $this->getRepository();

    $branches = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository)
      ->execute();

    $this->fillCommitCache(mpull($branches, 'getCommitIdentifier'));

    $refs = array();
    foreach ($branches as $branch) {
      // NOTE: Mercurial branches may have multiple heads, so the names may
      // not be unique.
      $name = $branch->getShortName();
      $commit = $branch->getCommitIdentifier();

      $this->log(pht('Examining branch "%s" head "%s".', $name, $commit));
      if (!$repository->shouldTrackBranch($name)) {
        $this->log(pht('Skipping, branch is untracked.'));
        continue;
      }

      if ($this->isKnownCommit($commit)) {
        $this->log(pht('Skipping, this head is a known commit.'));
        continue;
      }

      $this->log(pht('Looking for new commits.'));

      $branch_refs = $this->discoverStreamAncestry(
        new PhabricatorMercurialGraphStream($repository, $commit),
        $commit,
        $close_immediately = true);

      $this->didDiscoverRefs($branch_refs);

      $refs[] = $branch_refs;
    }

    return array_mergev($refs);
  }


/* -(  Internals  )---------------------------------------------------------- */


  private function discoverStreamAncestry(
    PhabricatorRepositoryGraphStream $stream,
    $commit,
    $close_immediately) {

    $discover = array($commit);
    $graph = array();
    $seen = array();

    // Find all the reachable, undiscovered commits. Build a graph of the
    // edges.
    while ($discover) {
      $target = array_pop($discover);

      if (empty($graph[$target])) {
        $graph[$target] = array();
      }

      $parents = $stream->getParents($target);
      foreach ($parents as $parent) {
        if ($this->isKnownCommit($parent)) {
          continue;
        }

        $graph[$target][$parent] = true;

        if (empty($seen[$parent])) {
          $seen[$parent] = true;
          $discover[] = $parent;
        }
      }
    }

    // Now, sort them topographically.
    $commits = $this->reduceGraph($graph);

    $refs = array();
    foreach ($commits as $commit) {
      $refs[] = id(new PhabricatorRepositoryCommitRef())
        ->setIdentifier($commit)
        ->setEpoch($stream->getCommitDate($commit))
        ->setCanCloseImmediately($close_immediately)
        ->setParents($stream->getParents($commit));
    }

    return $refs;
  }


  private function reduceGraph(array $edges) {
    foreach ($edges as $commit => $parents) {
      $edges[$commit] = array_keys($parents);
    }

    $graph = new PhutilDirectedScalarGraph();
    $graph->addNodes($edges);

    $commits = $graph->getTopographicallySortedNodes();

    // NOTE: We want the most ancestral nodes first, so we need to reverse the
    // list we get out of AbstractDirectedGraph.
    $commits = array_reverse($commits);

    return $commits;
  }


  private function isKnownCommit($identifier) {
    if (isset($this->commitCache[$identifier])) {
      return true;
    }

    if (isset($this->workingSet[$identifier])) {
      return true;
    }

    if ($this->repairMode) {
      // In repair mode, rediscover the entire repository, ignoring the
      // database state. We can hit the local cache above, but if we miss it
      // stop the script from going to the database cache.
      return false;
    }

    $this->fillCommitCache(array($identifier));

    return isset($this->commitCache[$identifier]);
  }

  private function fillCommitCache(array $identifiers) {
    if (!$identifiers) {
      return;
    }

    $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
      'repositoryID = %d AND commitIdentifier IN (%Ls)',
      $this->getRepository()->getID(),
      $identifiers);

    foreach ($commits as $commit) {
      $this->commitCache[$commit->getCommitIdentifier()] = true;
    }

    while (count($this->commitCache) > self::MAX_COMMIT_CACHE_SIZE) {
      array_shift($this->commitCache);
    }
  }

  /**
   * Sort branches so we process closeable branches first. This makes the
   * whole import process a little cheaper, since we can close these commits
   * the first time through rather than catching them in the refs step.
   *
   * @task internal
   *
   * @param   list<DiffusionRepositoryRef> List of branch heads.
   * @return  list<DiffusionRepositoryRef> Sorted list of branch heads.
   */
  private function sortBranches(array $branches) {
    $repository = $this->getRepository();

    $head_branches = array();
    $tail_branches = array();
    foreach ($branches as $branch) {
      $name = $branch->getShortName();

      if ($repository->shouldAutocloseBranch($name)) {
        $head_branches[] = $branch;
      } else {
        $tail_branches[] = $branch;
      }
    }

    return array_merge($head_branches, $tail_branches);
  }


  private function recordCommit(
    PhabricatorRepository $repository,
    $commit_identifier,
    $epoch,
    $close_immediately,
    array $parents) {

    $commit = new PhabricatorRepositoryCommit();
    $commit->setRepositoryID($repository->getID());
    $commit->setCommitIdentifier($commit_identifier);
    $commit->setEpoch($epoch);
    if ($close_immediately) {
      $commit->setImportStatus(PhabricatorRepositoryCommit::IMPORTED_CLOSEABLE);
    }

    $data = new PhabricatorRepositoryCommitData();

    $conn_w = $repository->establishConnection('w');

    try {

      // If this commit has parents, look up their IDs. The parent commits
      // should always exist already.

      $parent_ids = array();
      if ($parents) {
        $parent_rows = queryfx_all(
          $conn_w,
          'SELECT id, commitIdentifier FROM %T
            WHERE commitIdentifier IN (%Ls) AND repositoryID = %d',
          $commit->getTableName(),
          $parents,
          $repository->getID());

        $parent_map = ipull($parent_rows, 'id', 'commitIdentifier');

        foreach ($parents as $parent) {
          if (empty($parent_map[$parent])) {
            throw new Exception(
              pht('Unable to identify parent "%s"!', $parent));
          }
          $parent_ids[] = $parent_map[$parent];
        }
      } else {
        // Write an explicit 0 so we can distinguish between "really no
        // parents" and "data not available".
        if (!$repository->isSVN()) {
          $parent_ids = array(0);
        }
      }

      $commit->openTransaction();
        $commit->save();

        $data->setCommitID($commit->getID());
        $data->save();

        foreach ($parent_ids as $parent_id) {
          queryfx(
            $conn_w,
            'INSERT IGNORE INTO %T (childCommitID, parentCommitID)
              VALUES (%d, %d)',
            PhabricatorRepository::TABLE_PARENTS,
            $commit->getID(),
            $parent_id);
        }
      $commit->saveTransaction();

      $this->insertTask($repository, $commit);

      queryfx(
        $conn_w,
        'INSERT INTO %T (repositoryID, size, lastCommitID, epoch)
          VALUES (%d, 1, %d, %d)
          ON DUPLICATE KEY UPDATE
            size = size + 1,
            lastCommitID =
              IF(VALUES(epoch) > epoch, VALUES(lastCommitID), lastCommitID),
            epoch = IF(VALUES(epoch) > epoch, VALUES(epoch), epoch)',
        PhabricatorRepository::TABLE_SUMMARY,
        $repository->getID(),
        $commit->getID(),
        $epoch);

      if ($this->repairMode) {
        // Normally, the query should throw a duplicate key exception. If we
        // reach this in repair mode, we've actually performed a repair.
        $this->log(pht('Repaired commit "%s".', $commit_identifier));
      }

      PhutilEventEngine::dispatchEvent(
        new PhabricatorEvent(
          PhabricatorEventType::TYPE_DIFFUSION_DIDDISCOVERCOMMIT,
          array(
            'repository'  => $repository,
            'commit'      => $commit,
          )));



    } catch (AphrontDuplicateKeyQueryException $ex) {
      $commit->killTransaction();
      // Ignore. This can happen because we discover the same new commit
      // more than once when looking at history, or because of races or
      // data inconsistency or cosmic radiation; in any case, we're still
      // in a good state if we ignore the failure.
    }
  }

  private function didDiscoverRefs(array $refs) {
    foreach ($refs as $ref) {
      $this->workingSet[$ref->getIdentifier()] = true;
    }
  }

  private function insertTask(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    $data = array()) {

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

    $data['commitID'] = $commit->getID();

    PhabricatorWorker::scheduleTask($class, $data);
  }

  private function isInitialImport(array $refs) {
    $commit_count = count($refs);

    if ($commit_count <= PhabricatorRepository::IMPORT_THRESHOLD) {
      // If we fetched a small number of commits, assume it's an initial
      // commit or a stack of a few initial commits.
      return false;
    }

    $viewer = $this->getViewer();
    $repository = $this->getRepository();

    $any_commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepository($repository)
      ->setLimit(1)
      ->execute();

    if ($any_commits) {
      // If the repository already has commits, this isn't an import.
      return false;
    }

    return true;
  }


  private function getObservedVersion(PhabricatorRepository $repository) {
    if ($repository->isHosted()) {
      return null;
    }

    if ($repository->isGit()) {
      return $this->getGitObservedVersion($repository);
    }

    return null;
  }

  private function getGitObservedVersion(PhabricatorRepository $repository) {
    $refs = id(new DiffusionLowLevelGitRefQuery())
     ->setRepository($repository)
     ->execute();
    if (!$refs) {
      return null;
    }

    // In Git, the observed version is the most recently discovered commit
    // at any repository HEAD. It's possible for this to regress temporarily
    // if a branch is pushed and then deleted. This is acceptable because it
    // doesn't do anything meaningfully bad and will fix itself on the next
    // push.

    $ref_identifiers = mpull($refs, 'getCommitIdentifier');
    $ref_identifiers = array_fuse($ref_identifiers);

    $version = queryfx_one(
      $repository->establishConnection('w'),
      'SELECT MAX(id) version FROM %T WHERE repositoryID = %d
        AND commitIdentifier IN (%Ls)',
      id(new PhabricatorRepositoryCommit())->getTableName(),
      $repository->getID(),
      $ref_identifiers);

    if (!$version) {
      return null;
    }

    return (int)$version['version'];
  }

}
