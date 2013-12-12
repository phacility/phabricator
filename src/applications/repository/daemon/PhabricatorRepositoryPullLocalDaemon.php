<?php

/**
 * Run pull commands on local working copies to keep them up to date. This
 * daemon handles all repository types.
 *
 * By default, the daemon pulls **every** repository. If you want it to be
 * responsible for only some repositories, you can launch it with a list of
 * PHIDs or callsigns:
 *
 *   ./phd launch repositorypulllocal -- X Q Z
 *
 * You can also launch a daemon which is responsible for all //but// one or
 * more repositories:
 *
 *   ./phd launch repositorypulllocal -- --not A --not B
 *
 * If you have a very large number of repositories and some aren't being pulled
 * as frequently as you'd like, you can either change the pull frequency of
 * the less-important repositories to a larger number (so the daemon will skip
 * them more often) or launch one daemon for all the less-important repositories
 * and one for the more important repositories (or one for each more important
 * repository).
 *
 * @task pull   Pulling Repositories
 * @task git    Git Implementation
 * @task hg     Mercurial Implementation
 */
final class PhabricatorRepositoryPullLocalDaemon
  extends PhabricatorDaemon {

  private $commitCache = array();
  private $repair;
  private $discoveryEngines = array();

  public function setRepair($repair) {
    $this->repair = $repair;
    return $this;
  }


/* -(  Pulling Repositories  )----------------------------------------------- */


  /**
   * @task pull
   */
  public function run() {
    $argv = $this->getArgv();
    array_unshift($argv, __CLASS__);
    $args = new PhutilArgumentParser($argv);
    $args->parse(
      array(
        array(
          'name'      => 'no-discovery',
          'help'      => 'Pull only, without discovering commits.',
        ),
        array(
          'name'      => 'not',
          'param'     => 'repository',
          'repeat'    => true,
          'help'      => 'Do not pull __repository__.',
        ),
        array(
          'name'      => 'repositories',
          'wildcard'  => true,
          'help'      => 'Pull specific __repositories__ instead of all.',
        ),
      ));

    $no_discovery   = $args->getArg('no-discovery');
    $repo_names     = $args->getArg('repositories');
    $exclude_names  = $args->getArg('not');

    // Each repository has an individual pull frequency; after we pull it,
    // wait that long to pull it again. When we start up, try to pull everything
    // serially.
    $retry_after = array();

    $min_sleep = 15;

    while (true) {
      $repositories = $this->loadRepositories($repo_names);
      if ($exclude_names) {
        $exclude = $this->loadRepositories($exclude_names);
        $repositories = array_diff_key($repositories, $exclude);
      }

      // Shuffle the repositories, then re-key the array since shuffle()
      // discards keys. This is mostly for startup, we'll use soft priorities
      // later.
      shuffle($repositories);
      $repositories = mpull($repositories, null, 'getID');

      // If any repositories have the NEEDS_UPDATE flag set, pull them
      // as soon as possible.
      $type_need_update = PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE;
      $need_update_messages = id(new PhabricatorRepositoryStatusMessage())
        ->loadAllWhere('statusType = %s', $type_need_update);
      foreach ($need_update_messages as $message) {
        $retry_after[$message->getRepositoryID()] = time();
      }

      // If any repositories were deleted, remove them from the retry timer map
      // so we don't end up with a retry timer that never gets updated and
      // causes us to sleep for the minimum amount of time.
      $retry_after = array_select_keys(
        $retry_after,
        array_keys($repositories));

      // Assign soft priorities to repositories based on how frequently they
      // should pull again.
      asort($retry_after);
      $repositories = array_select_keys(
        $repositories,
        array_keys($retry_after)) + $repositories;

      foreach ($repositories as $id => $repository) {
        $after = idx($retry_after, $id, 0);
        if ($after > time()) {
          continue;
        }

        $tracked = $repository->isTracked();
        if (!$tracked) {
          continue;
        }

        $callsign = $repository->getCallsign();

        try {
          $this->log("Updating repository '{$callsign}'.");

          id(new PhabricatorRepositoryPullEngine())
            ->setRepository($repository)
            ->pullRepository();

          if (!$no_discovery) {
            // TODO: It would be nice to discover only if we pulled something,
            // but this isn't totally trivial. It's slightly more complicated
            // with hosted repositories, too.

            $lock_name = get_class($this).':'.$callsign;
            $lock = PhabricatorGlobalLock::newLock($lock_name);
            $lock->lock();

            $repository->writeStatusMessage(
              PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
              null);

            try {
              $this->discoverRepository($repository);
              $repository->writeStatusMessage(
                PhabricatorRepositoryStatusMessage::TYPE_FETCH,
                PhabricatorRepositoryStatusMessage::CODE_OKAY);
            } catch (Exception $ex) {
              $repository->writeStatusMessage(
                PhabricatorRepositoryStatusMessage::TYPE_FETCH,
                PhabricatorRepositoryStatusMessage::CODE_ERROR,
                array(
                  'message' => pht(
                    'Error updating working copy: %s', $ex->getMessage()),
                ));
              $lock->unlock();
              throw $ex;
            }

            $lock->unlock();
          }

          $sleep_for = $repository->getDetail('pull-frequency', $min_sleep);
          $retry_after[$id] = time() + $sleep_for;
        } catch (PhutilLockException $ex) {
          $retry_after[$id] = time() + $min_sleep;
          $this->log("Failed to acquire lock.");
        } catch (Exception $ex) {
          $retry_after[$id] = time() + $min_sleep;

          $proxy = new PhutilProxyException(
            "Error while fetching changes to the '{$callsign}' repository.",
            $ex);
          phlog($proxy);
        }

        $this->stillWorking();
      }

      if ($retry_after) {
        $sleep_until = max(min($retry_after), time() + $min_sleep);
      } else {
        $sleep_until = time() + $min_sleep;
      }

      $this->sleep($sleep_until - time());
    }
  }


  /**
   * @task pull
   */
  protected function loadRepositories(array $names) {
    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer());

    if ($names) {
      $query->withCallsigns($names);
    }

    $repos = $query->execute();

    if ($names) {
      $by_callsign = mpull($repos, null, 'getCallsign');
      foreach ($names as $name) {
        if (empty($by_callsign[$name])) {
          throw new Exception(
            "No repository exists with callsign '{$name}'!");
        }
      }
    }

    return $repos;
  }

  public function discoverRepository(PhabricatorRepository $repository) {
    $vcs = $repository->getVersionControlSystem();

    $result = null;
    $refs = null;
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->executeGitDiscover($repository);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $refs = $this->getDiscoveryEngine($repository)
          ->discoverCommits();
        break;
      default:
        throw new Exception("Unknown VCS '{$vcs}'!");
    }

    if ($refs !== null) {
      foreach ($refs as $ref) {
        $this->recordCommit(
          $repository,
          $ref->getIdentifier(),
          $ref->getEpoch(),
          $ref->getBranch());
      }
    }

    $this->checkIfRepositoryIsFullyImported($repository);

    try {
      $this->pushToMirrors($repository);
    } catch (Exception $ex) {
      // TODO: We should report these into the UI properly, but for
      // now just complain. These errors are much less severe than
      // pull errors.
      phlog($ex);
    }

    if ($refs !== null) {
      return (bool)count($refs);
    } else {
      return $result;
    }
  }

  private function getDiscoveryEngine(PhabricatorRepository $repository) {
    $id = $repository->getID();
    if (empty($this->discoveryEngines[$id])) {
      $engine = id(new PhabricatorRepositoryDiscoveryEngine())
          ->setRepository($repository)
          ->setVerbose($this->getVerbose())
          ->setRepairMode($this->repair);

      $this->discoveryEngines[$id] = $engine;
    }
    return $this->discoveryEngines[$id];
  }

  private function isKnownCommit(
    PhabricatorRepository $repository,
    $target) {

    if ($this->getCache($repository, $target)) {
      return true;
    }

    if ($this->repair) {
      // In repair mode, rediscover the entire repository, ignoring the
      // database state. We can hit the local cache above, but if we miss it
      // stop the script from going to the database cache.
      return false;
    }

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $target);

    if (!$commit) {
      return false;
    }

    $this->setCache($repository, $target);
    while (count($this->commitCache) > 2048) {
      array_shift($this->commitCache);
    }

    return true;
  }

  private function isKnownCommitOnAnyAutocloseBranch(
    PhabricatorRepository $repository,
    $target) {

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $target);

    if (!$commit) {
      $callsign = $repository->getCallsign();

      $console = PhutilConsole::getConsole();
      $console->writeErr(
        "WARNING: Repository '%s' is missing commits ('%s' is missing from ".
        "history). Run '%s' to repair the repository.\n",
        $callsign,
        $target,
        "bin/repository discover --repair {$callsign}");

      return false;
    }

    $data = $commit->loadCommitData();
    if (!$data) {
      return false;
    }

    if ($repository->shouldAutocloseCommit($commit, $data)) {
      return true;
    }

    return false;
  }

  private function recordCommit(
    PhabricatorRepository $repository,
    $commit_identifier,
    $epoch,
    $branch = null) {

    $commit = new PhabricatorRepositoryCommit();
    $commit->setRepositoryID($repository->getID());
    $commit->setCommitIdentifier($commit_identifier);
    $commit->setEpoch($epoch);

    $data = new PhabricatorRepositoryCommitData();
    if ($branch) {
      $data->setCommitDetail('seenOnBranches', array($branch));
    }

    try {
      $commit->openTransaction();
        $commit->save();
        $data->setCommitID($commit->getID());
        $data->save();
      $commit->saveTransaction();

      $this->insertTask($repository, $commit);

      queryfx(
        $repository->establishConnection('w'),
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

      if ($this->repair) {
        // Normally, the query should throw a duplicate key exception. If we
        // reach this in repair mode, we've actually performed a repair.
        $this->log("Repaired commit '{$commit_identifier}'.");
      }

      $this->setCache($repository, $commit_identifier);

      PhutilEventEngine::dispatchEvent(
        new PhabricatorEvent(
          PhabricatorEventType::TYPE_DIFFUSION_DIDDISCOVERCOMMIT,
          array(
            'repository'  => $repository,
            'commit'      => $commit,
          )));

    } catch (AphrontQueryDuplicateKeyException $ex) {
      $commit->killTransaction();
      // Ignore. This can happen because we discover the same new commit
      // more than once when looking at history, or because of races or
      // data inconsistency or cosmic radiation; in any case, we're still
      // in a good state if we ignore the failure.
      $this->setCache($repository, $commit_identifier);
    }
  }

  private function updateCommit(
    PhabricatorRepository $repository,
    $commit_identifier,
    $branch) {

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $commit_identifier);

    if (!$commit) {
      // This can happen if the phabricator DB doesn't have the commit info,
      // or the commit is so big that phabricator couldn't parse it. In this
      // case we just ignore it.
      return;
    }

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    if (!$data) {
      $data = new PhabricatorRepositoryCommitData();
      $data->setCommitID($commit->getID());
    }
    $branches = $data->getCommitDetail('seenOnBranches', array());
    $branches[] = $branch;
    $data->setCommitDetail('seenOnBranches', $branches);
    $data->save();

    $this->insertTask(
      $repository,
      $commit,
      array(
        'only' => true
      ));
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
        throw new Exception("Unknown repository type '{$vcs}'!");
    }

    $data['commitID'] = $commit->getID();

    PhabricatorWorker::scheduleTask($class, $data);
  }


  private function setCache(
    PhabricatorRepository $repository,
    $commit_identifier) {

    $key = $this->getCacheKey($repository, $commit_identifier);
    $this->commitCache[$key] = true;
  }

  private function getCache(
    PhabricatorRepository $repository,
    $commit_identifier) {

    $key = $this->getCacheKey($repository, $commit_identifier);
    return idx($this->commitCache, $key, false);
  }

  private function getCacheKey(
    PhabricatorRepository $repository,
    $commit_identifier) {

    return $repository->getID().':'.$commit_identifier;
  }

  private function checkIfRepositoryIsFullyImported(
    PhabricatorRepository $repository) {

    // Check if the repository has the "Importing" flag set. We want to clear
    // the flag if we can.
    $importing = $repository->getDetail('importing');
    if (!$importing) {
      // This repository isn't marked as "Importing", so we're done.
      return;
    }

    // Look for any commit which hasn't imported.
    $unparsed_commit = queryfx_one(
      $repository->establishConnection('r'),
      'SELECT * FROM %T WHERE repositoryID = %d AND importStatus != %d
        LIMIT 1',
      id(new PhabricatorRepositoryCommit())->getTableName(),
      $repository->getID(),
      PhabricatorRepositoryCommit::IMPORTED_ALL);
    if ($unparsed_commit) {
      // We found a commit which still needs to import, so we can't clear the
      // flag.
      return;
    }

    // Clear the "importing" flag.
    $repository->openTransaction();
      $repository->beginReadLocking();
        $repository = $repository->reload();
        $repository->setDetail('importing', false);
        $repository->save();
      $repository->endReadLocking();
    $repository->saveTransaction();
  }

/* -(  Git Implementation  )------------------------------------------------- */


  /**
   * @task git
   */
  private function executeGitDiscover(
    PhabricatorRepository $repository) {

    if (!$repository->isHosted()) {
      $this->verifyOrigin($repository);
    }

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withIsOriginBranch(true)
      ->execute();

    $branches = mpull($refs, 'getCommitIdentifier', 'getShortName');

    if (!$branches) {
      // This repository has no branches at all, so we don't need to do
      // anything. Generally, this means the repository is empty.
      return;
    }

    $callsign = $repository->getCallsign();

    $tracked_something = false;

    $this->log("Discovering commits in repository '{$callsign}'...");
    foreach ($branches as $name => $commit) {
      $this->log("Examining branch '{$name}', at {$commit}.");
      if (!$repository->shouldTrackBranch($name)) {
        $this->log("Skipping, branch is untracked.");
        continue;
      }

      $tracked_something = true;

      if ($this->isKnownCommit($repository, $commit)) {
        $this->log("Skipping, HEAD is known.");
        continue;
      }

      $this->log("Looking for new commits.");
      $this->executeGitDiscoverCommit($repository, $commit, $name, false);
    }

    if (!$tracked_something) {
      $repo_name = $repository->getName();
      $repo_callsign = $repository->getCallsign();
      throw new Exception(
        "Repository r{$repo_callsign} '{$repo_name}' has no tracked branches! ".
        "Verify that your branch filtering settings are correct.");
    }


    $this->log("Discovering commits on autoclose branches...");
    foreach ($branches as $name => $commit) {
      $this->log("Examining branch '{$name}', at {$commit}'.");
      if (!$repository->shouldTrackBranch($name)) {
        $this->log("Skipping, branch is untracked.");
        continue;
      }

      if (!$repository->shouldAutocloseBranch($name)) {
        $this->log("Skipping, branch is not autoclose.");
        continue;
      }

      if ($this->isKnownCommitOnAnyAutocloseBranch($repository, $commit)) {
        $this->log("Skipping, commit is known on an autoclose branch.");
        continue;
      }

      $this->log("Looking for new autoclose commits.");
      $this->executeGitDiscoverCommit($repository, $commit, $name, true);
    }
  }


  /**
   * @task git
   */
  private function executeGitDiscoverCommit(
    PhabricatorRepository $repository,
    $commit,
    $branch,
    $autoclose) {

    $discover = array($commit);
    $insert = array($commit);

    $seen_parent = array();

    $stream = new PhabricatorGitGraphStream($repository, $commit);

    while (true) {
      $target = array_pop($discover);
      $parents = $stream->getParents($target);
      foreach ($parents as $parent) {
        if (isset($seen_parent[$parent])) {
          // We end up in a loop here somehow when we parse Arcanist if we
          // don't do this. TODO: Figure out why and draw a pretty diagram
          // since it's not evident how parsing a DAG with this causes the
          // loop to stop terminating.
          continue;
        }
        $seen_parent[$parent] = true;
        if ($autoclose) {
          $known = $this->isKnownCommitOnAnyAutocloseBranch(
            $repository,
            $parent);
        } else {
          $known = $this->isKnownCommit($repository, $parent);
        }
        if (!$known) {
          $this->log("Discovered commit '{$parent}'.");
          $discover[] = $parent;
          $insert[] = $parent;
        }
      }
      if (empty($discover)) {
        break;
      }
    }

    $n = count($insert);
    if ($autoclose) {
      $this->log("Found {$n} new autoclose commits on branch '{$branch}'.");
    } else {
      $this->log("Found {$n} new commits on branch '{$branch}'.");
    }

    while (true) {
      $target = array_pop($insert);
      $epoch = $stream->getCommitDate($target);
      $epoch = trim($epoch);

      if ($autoclose) {
        $this->updateCommit($repository, $target, $branch);
      } else {
        $this->recordCommit($repository, $target, $epoch, $branch);
      }

      if (empty($insert)) {
        break;
      }
    }
  }


  /**
   * Verify that the "origin" remote exists, and points at the correct URI.
   *
   * This catches or corrects some types of misconfiguration, and also repairs
   * an issue where Git 1.7.1 does not create an "origin" for `--bare` clones.
   * See T4041.
   *
   * @param   PhabricatorRepository Repository to verify.
   * @return  void
   */
  private function verifyOrigin(PhabricatorRepository $repository) {
    list($remotes) = $repository->execxLocalCommand(
      'remote show -n origin');

    $matches = null;
    if (!preg_match('/^\s*Fetch URL:\s*(.*?)\s*$/m', $remotes, $matches)) {
      throw new Exception(
        "Expected 'Fetch URL' in 'git remote show -n origin'.");
    }

    $remote_uri = $matches[1];
    $expect_remote = $repository->getRemoteURI();

    if ($remote_uri == "origin") {
      // If a remote does not exist, git pretends it does and prints out a
      // made up remote where the URI is the same as the remote name. This is
      // definitely not correct.

      // Possibly, we should use `git remote --verbose` instead, which does not
      // suffer from this problem (but is a little more complicated to parse).
      $valid = false;
      $exists = false;
    } else {
      $valid = self::isSameGitOrigin($remote_uri, $expect_remote);
      $exists = true;
    }

    if (!$valid) {
      if (!$exists) {
        // If there's no "origin" remote, just create it regardless of how
        // strongly we own the working copy. There is almost no conceivable
        // scenario in which this could do damage.
        $this->log(
          pht(
            'Remote "origin" does not exist. Creating "origin", with '.
            'URI "%s".',
            $expect_remote));
        $repository->execxLocalCommand(
          'remote add origin %s',
          $expect_remote);

        // NOTE: This doesn't fetch the origin (it just creates it), so we won't
        // know about origin branches until the next "pull" happens. That's fine
        // for our purposes, but might impact things in the future.
      } else {
        if ($repository->canDestroyWorkingCopy()) {
          // Bad remote, but we can try to repair it.
          $this->log(
            pht(
              'Remote "origin" exists, but is pointed at the wrong URI, "%s". '.
              'Resetting origin URI to "%s.',
              $remote_uri,
              $expect_remote));
          $repository->execxLocalCommand(
            'remote set-url origin %s',
            $expect_remote);
        } else {
          // Bad remote and we aren't comfortable repairing it.
          $message = pht(
            'Working copy at "%s" has a mismatched origin URI, "%s". '.
            'The expected origin URI is "%s". Fix your configuration, or '.
            'set the remote URI correctly. To avoid breaking anything, '.
            'Phabricator will not automatically fix this.',
            $repository->getLocalPath(),
            $remote_uri,
            $expect_remote);
          throw new Exception($message);
        }
      }
    }
  }



  /**
   * @task git
   */
  public static function isSameGitOrigin($remote, $expect) {
    $remote_path = self::getPathFromGitURI($remote);
    $expect_path = self::getPathFromGitURI($expect);

    $remote_match = self::executeGitNormalizePath($remote_path);
    $expect_match = self::executeGitNormalizePath($expect_path);

    return ($remote_match == $expect_match);
  }

  private static function getPathFromGitURI($raw_uri) {
    $uri = new PhutilURI($raw_uri);
    if ($uri->getProtocol()) {
      return $uri->getPath();
    }

    $uri = new PhutilGitURI($raw_uri);
    if ($uri->getDomain()) {
      return $uri->getPath();
    }

    return $raw_uri;
  }


  /**
   * @task git
   */
  private static function executeGitNormalizePath($path) {
    // Strip away "/" and ".git", so similar paths correctly match.

    $path = trim($path, '/');
    $path = preg_replace('/\.git$/', '', $path);
    return $path;
  }


  private function pushToMirrors(PhabricatorRepository $repository) {
    if (!$repository->canMirror()) {
      return;
    }

    $mirrors = id(new PhabricatorRepositoryMirrorQuery())
      ->setViewer($this->getViewer())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();

    // TODO: This is a little bit janky, but we don't have first-class
    // infrastructure for running remote commands against an arbitrary remote
    // right now. Just make an emphemeral copy of the repository and muck with
    // it a little bit. In the medium term, we should pull this command stuff
    // out and use it here and for "Land to ...".

    $proxy = clone $repository;
    $proxy->makeEphemeral();

    $proxy->setDetail('hosting-enabled', false);
    foreach ($mirrors as $mirror) {
      $proxy->setDetail('remote-uri', $mirror->getRemoteURI());
      $proxy->setCredentialPHID($mirror->getCredentialPHID());

      $this->log(pht('Pushing to remote "%s"...', $mirror->getRemoteURI()));

      if (!$proxy->isGit()) {
        throw new Exception('Unsupported VCS!');
      }

      $future = $proxy->getRemoteCommandFuture(
        'push --verbose --mirror -- %P',
        $proxy->getRemoteURIEnvelope());

      $future
        ->setCWD($proxy->getLocalPath())
        ->resolvex();
    }
  }
}
