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

            try {
              $repository->writeStatusMessage(
                PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
                null);
              $this->discoverRepository($repository);
              $this->updateRepositoryRefs($repository);
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
    $refs = $this->getDiscoveryEngine($repository)
      ->discoverCommits();

    foreach ($refs as $ref) {
      $this->recordCommit(
        $repository,
        $ref->getIdentifier(),
        $ref->getEpoch(),
        $ref->getCanCloseImmediately());
    }

    $this->checkIfRepositoryIsFullyImported($repository);

    try {
      $this->pushToMirrors($repository);
    } catch (Exception $ex) {
      // TODO: We should report these into the UI properly, but for
      // now just complain. These errors are much less severe than
      // pull errors.
      $proxy = new PhutilProxyException(
        pht(
          'Error while pushing "%s" repository to a mirror.',
          $repository->getCallsign()),
        $ex);
      phlog($proxy);
    }

    return (bool)count($refs);
  }

  private function updateRepositoryRefs(PhabricatorRepository $repository) {
    id(new PhabricatorRepositoryRefEngine())
      ->setRepository($repository)
      ->updateRefs();
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

  private function recordCommit(
    PhabricatorRepository $repository,
    $commit_identifier,
    $epoch,
    $close_immediately) {

    $commit = new PhabricatorRepositoryCommit();
    $commit->setRepositoryID($repository->getID());
    $commit->setCommitIdentifier($commit_identifier);
    $commit->setEpoch($epoch);
    if ($close_immediately) {
      $commit->setImportStatus(PhabricatorRepositoryCommit::IMPORTED_CLOSEABLE);
    }

    $data = new PhabricatorRepositoryCommitData();

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
        throw new Exception("Unknown repository type '{$vcs}'!");
    }

    $data['commitID'] = $commit->getID();

    PhabricatorWorker::scheduleTask($class, $data);
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
