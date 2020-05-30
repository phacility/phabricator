<?php

/**
 * Run pull commands on local working copies to keep them up to date. This
 * daemon handles all repository types.
 *
 * By default, the daemon pulls **every** repository. If you want it to be
 * responsible for only some repositories, you can launch it with a list of
 * repositories:
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
 */
final class PhabricatorRepositoryPullLocalDaemon
  extends PhabricatorDaemon {

  private $statusMessageCursor = 0;

/* -(  Pulling Repositories  )----------------------------------------------- */


  /**
   * @task pull
   */
  protected function run() {
    $argv = $this->getArgv();
    array_unshift($argv, __CLASS__);
    $args = new PhutilArgumentParser($argv);
    $args->parse(
      array(
        array(
          'name'      => 'no-discovery',
          'help'      => pht('Pull only, without discovering commits.'),
        ),
        array(
          'name'      => 'not',
          'param'     => 'repository',
          'repeat'    => true,
          'help'      => pht('Do not pull __repository__.'),
        ),
        array(
          'name'      => 'repositories',
          'wildcard'  => true,
          'help'      => pht('Pull specific __repositories__ instead of all.'),
        ),
      ));

    $no_discovery   = $args->getArg('no-discovery');
    $include = $args->getArg('repositories');
    $exclude = $args->getArg('not');

    // Each repository has an individual pull frequency; after we pull it,
    // wait that long to pull it again. When we start up, try to pull everything
    // serially.
    $retry_after = array();

    $min_sleep = 15;
    $max_sleep = phutil_units('5 minutes in seconds');
    $max_futures = 4;
    $futures = array();
    $queue = array();

    $future_pool = new FuturePool();

    $future_pool->getIteratorTemplate()
      ->setUpdateInterval($min_sleep);

    $sync_wait = phutil_units('2 minutes in seconds');
    $last_sync = array();

    while (!$this->shouldExit()) {
      PhabricatorCaches::destroyRequestCache();
      $device = AlmanacKeys::getLiveDevice();

      $pullable = $this->loadPullableRepositories($include, $exclude, $device);

      // If any repositories have the NEEDS_UPDATE flag set, pull them
      // as soon as possible.
      $need_update_messages = $this->loadRepositoryUpdateMessages(true);
      foreach ($need_update_messages as $message) {
        $repo = idx($pullable, $message->getRepositoryID());
        if (!$repo) {
          continue;
        }

        $this->log(
          pht(
            'Got an update message for repository "%s"!',
            $repo->getMonogram()));

        $retry_after[$message->getRepositoryID()] = time();
      }

      if ($device) {
        $unsynchronized = $this->loadUnsynchronizedRepositories($device);
        $now = PhabricatorTime::getNow();
        foreach ($unsynchronized as $repository) {
          $id = $repository->getID();

          $this->log(
            pht(
              'Cluster repository ("%s") is out of sync on this node ("%s").',
              $repository->getDisplayName(),
              $device->getName()));

          // Don't let out-of-sync conditions trigger updates too frequently,
          // since we don't want to get trapped in a death spiral if sync is
          // failing.
          $sync_at = idx($last_sync, $id, 0);
          $wait_duration = ($now - $sync_at);
          if ($wait_duration < $sync_wait) {
            $this->log(
              pht(
                'Skipping forced out-of-sync update because the last update '.
                'was too recent (%s seconds ago).',
                $wait_duration));
            continue;
          }

          $last_sync[$id] = $now;
          $retry_after[$id] = $now;
        }
      }

      // If any repositories were deleted, remove them from the retry timer map
      // so we don't end up with a retry timer that never gets updated and
      // causes us to sleep for the minimum amount of time.
      $retry_after = array_select_keys(
        $retry_after,
        array_keys($pullable));

      // Figure out which repositories we need to queue for an update.
      foreach ($pullable as $id => $repository) {
        $now = PhabricatorTime::getNow();
        $display_name = $repository->getDisplayName();

        if (isset($futures[$id])) {
          $this->log(
            pht(
              'Repository "%s" is currently updating.',
              $display_name));
          continue;
        }

        if (isset($queue[$id])) {
          $this->log(
            pht(
              'Repository "%s" is already queued.',
              $display_name));
          continue;
        }

        $after = idx($retry_after, $id);
        if (!$after) {
          $smart_wait = $repository->loadUpdateInterval($min_sleep);
          $last_update = $this->loadLastUpdate($repository);

          $after = $last_update + $smart_wait;
          $retry_after[$id] = $after;

          $this->log(
            pht(
              'Scheduling repository "%s" with an update window of %s '.
              'second(s). Last update was %s second(s) ago.',
              $display_name,
              new PhutilNumber($smart_wait),
              new PhutilNumber($now - $last_update)));
        }

        if ($after > time()) {
          $this->log(
            pht(
              'Repository "%s" is not due for an update for %s second(s).',
              $display_name,
              new PhutilNumber($after - $now)));
          continue;
        }

        $this->log(
          pht(
            'Scheduling repository "%s" for an update (%s seconds overdue).',
            $display_name,
            new PhutilNumber($now - $after)));

        $queue[$id] = $after;
      }

      // Process repositories in the order they became candidates for updates.
      asort($queue);

      // Dequeue repositories until we hit maximum parallelism.
      while ($queue && (count($futures) < $max_futures)) {
        foreach ($queue as $id => $time) {
          $repository = idx($pullable, $id);
          if (!$repository) {
            $this->log(
              pht('Repository %s is no longer pullable; skipping.', $id));
            unset($queue[$id]);
            continue;
          }

          $display_name = $repository->getDisplayName();
          $this->log(
            pht(
              'Starting update for repository "%s".',
              $display_name));

          unset($queue[$id]);

          $future = $this->buildUpdateFuture(
            $repository,
            $no_discovery);

          $futures[$id] = $future->getFutureKey();

          $future_pool->addFuture($future);
          break;
        }
      }

      if ($queue) {
        $this->log(
          pht(
            'Not enough process slots to schedule the other %s '.
            'repository(s) for updates yet.',
            phutil_count($queue)));
      }

      if ($future_pool->hasFutures()) {
        while ($future_pool->hasFutures()) {
          $future = $future_pool->resolve();

          $this->stillWorking();

          if ($future === null) {
            $this->log(pht('Waiting for updates to complete...'));

            if ($this->loadRepositoryUpdateMessages()) {
              $this->log(pht('Interrupted by pending updates!'));
              break;
            }

            continue;
          }

          $future_key = $future->getFutureKey();
          $repository_id = null;
          foreach ($futures as $id => $key) {
            if ($key === $future_key) {
              $repository_id = $id;
              unset($futures[$id]);
              break;
            }
          }

          $retry_after[$repository_id] = $this->resolveUpdateFuture(
            $pullable[$repository_id],
            $future,
            $min_sleep);

          // We have a free slot now, so go try to fill it.
          break;
        }

        // Jump back into prioritization if we had any futures to deal with.
        continue;
      }

      $should_hibernate = $this->waitForUpdates($max_sleep, $retry_after);
      if ($should_hibernate) {
        break;
      }
    }

  }


  /**
   * @task pull
   */
  private function buildUpdateFuture(
    PhabricatorRepository $repository,
    $no_discovery) {

    $bin = dirname(phutil_get_library_root('phabricator')).'/bin/repository';

    $flags = array();
    if ($no_discovery) {
      $flags[] = '--no-discovery';
    }

    $monogram = $repository->getMonogram();
    $future = new ExecFuture('%s update %Ls -- %s', $bin, $flags, $monogram);

    // Sometimes, the underlying VCS commands will hang indefinitely. We've
    // observed this occasionally with GitHub, and other users have observed
    // it with other VCS servers.

    // To limit the damage this can cause, kill the update out after a
    // reasonable amount of time, under the assumption that it has hung.

    // Since it's hard to know what a "reasonable" amount of time is given that
    // users may be downloading a repository full of pirated movies over a
    // potato, these limits are fairly generous. Repositories exceeding these
    // limits can be manually pulled with `bin/repository update X`, which can
    // just run for as long as it wants.

    if ($repository->isImporting()) {
      $timeout = phutil_units('4 hours in seconds');
    } else {
      $timeout = phutil_units('15 minutes in seconds');
    }

    $future->setTimeout($timeout);

    // The default TERM inherited by this process is "unknown", which causes PHP
    // to produce a warning upon startup.  Override it to squash this output to
    // STDERR.
    $future->updateEnv('TERM', 'dumb');

    return $future;
  }


  /**
   * Check for repositories that should be updated immediately.
   *
   * With the `$consume` flag, an internal cursor will also be incremented so
   * that these messages are not returned by subsequent calls.
   *
   * @param bool Pass `true` to consume these messages, so the process will
   *   not see them again.
   * @return list<wild> Pending update messages.
   *
   * @task pull
   */
  private function loadRepositoryUpdateMessages($consume = false) {
    $type_need_update = PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE;
    $messages = id(new PhabricatorRepositoryStatusMessage())->loadAllWhere(
      'statusType = %s AND id > %d',
      $type_need_update,
      $this->statusMessageCursor);

    // Keep track of messages we've seen so that we don't load them again.
    // If we reload messages, we can get stuck a loop if we have a failing
    // repository: we update immediately in response to the message, but do
    // not clear the message because the update does not succeed. We then
    // immediately retry. Instead, messages are only permitted to trigger
    // an immediate update once.

    if ($consume) {
      foreach ($messages as $message) {
        $this->statusMessageCursor = max(
          $this->statusMessageCursor,
          $message->getID());
      }
    }

    return $messages;
  }


  /**
   * @task pull
   */
  private function loadLastUpdate(PhabricatorRepository $repository) {
    $table = new PhabricatorRepositoryStatusMessage();
    $conn = $table->establishConnection('r');

    $epoch = queryfx_one(
      $conn,
      'SELECT MAX(epoch) last_update FROM %T
        WHERE repositoryID = %d
          AND statusType IN (%Ls)',
      $table->getTableName(),
      $repository->getID(),
      array(
        PhabricatorRepositoryStatusMessage::TYPE_INIT,
        PhabricatorRepositoryStatusMessage::TYPE_FETCH,
      ));

    if ($epoch) {
      return (int)$epoch['last_update'];
    }

    return PhabricatorTime::getNow();
  }

  /**
   * @task pull
   */
  private function loadPullableRepositories(
    array $include,
    array $exclude,
    AlmanacDevice $device = null) {

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer());

    if ($include) {
      $query->withIdentifiers($include);
    }

    $repositories = $query->execute();
    $repositories = mpull($repositories, null, 'getPHID');

    if ($include) {
      $map = $query->getIdentifierMap();
      foreach ($include as $identifier) {
        if (empty($map[$identifier])) {
          throw new Exception(
            pht(
              'No repository "%s" exists!',
              $identifier));
        }
      }
    }

    if ($exclude) {
      $xquery = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withIdentifiers($exclude);

      $excluded_repos = $xquery->execute();
      $xmap = $xquery->getIdentifierMap();

      foreach ($exclude as $identifier) {
        if (empty($xmap[$identifier])) {
          throw new Exception(
            pht(
              'No repository "%s" exists!',
              $identifier));
        }
      }

      foreach ($excluded_repos as $excluded_repo) {
        unset($repositories[$excluded_repo->getPHID()]);
      }
    }

    foreach ($repositories as $key => $repository) {
      if (!$repository->isTracked()) {
        unset($repositories[$key]);
      }
    }

    $viewer = $this->getViewer();

    $filter = id(new DiffusionLocalRepositoryFilter())
      ->setViewer($viewer)
      ->setDevice($device)
      ->setRepositories($repositories);

    $repositories = $filter->execute();

    foreach ($filter->getRejectionReasons() as $reason) {
      $this->log($reason);
    }

    // Shuffle the repositories, then re-key the array since shuffle()
    // discards keys. This is mostly for startup, we'll use soft priorities
    // later.
    shuffle($repositories);
    $repositories = mpull($repositories, null, 'getID');

    return $repositories;
  }


  /**
   * @task pull
   */
  private function resolveUpdateFuture(
    PhabricatorRepository $repository,
    ExecFuture $future,
    $min_sleep) {

    $display_name = $repository->getDisplayName();

    $this->log(pht('Resolving update for "%s".', $display_name));

    try {
      list($stdout, $stderr) = $future->resolvex();
    } catch (Exception $ex) {
      $proxy = new PhutilProxyException(
        pht(
          'Error while updating the "%s" repository.',
          $display_name),
        $ex);
      phlog($proxy);

      $smart_wait = $repository->loadUpdateInterval($min_sleep);
      return PhabricatorTime::getNow() + $smart_wait;
    }

    if (strlen($stderr)) {
      $stderr_msg = pht(
        'Unexpected output while updating repository "%s": %s',
        $display_name,
        $stderr);
      phlog($stderr_msg);
    }

    $smart_wait = $repository->loadUpdateInterval($min_sleep);

    $this->log(
      pht(
        'Based on activity in repository "%s", considering a wait of %s '.
        'seconds before update.',
        $display_name,
        new PhutilNumber($smart_wait)));

    return PhabricatorTime::getNow() + $smart_wait;
  }



  /**
   * Sleep for a short period of time, waiting for update messages from the
   *
   *
   * @task pull
   */
  private function waitForUpdates($min_sleep, array $retry_after) {
    $this->log(
      pht('No repositories need updates right now, sleeping...'));

    $sleep_until = time() + $min_sleep;
    if ($retry_after) {
      $sleep_until = min($sleep_until, min($retry_after));
    }

    while (($sleep_until - time()) > 0) {
      $sleep_duration = ($sleep_until - time());

      if ($this->shouldHibernate($sleep_duration)) {
        return true;
      }

      $this->log(
        pht(
          'Sleeping for %s more second(s)...',
          new PhutilNumber($sleep_duration)));

      $this->sleep(1);

      if ($this->shouldExit()) {
        $this->log(pht('Awakened from sleep by graceful shutdown!'));
        return false;
      }

      if ($this->loadRepositoryUpdateMessages()) {
        $this->log(pht('Awakened from sleep by pending updates!'));
        break;
      }
    }

    return false;
  }

  private function loadUnsynchronizedRepositories(AlmanacDevice $device) {
    $viewer = $this->getViewer();
    $table = new PhabricatorRepositoryWorkingCopyVersion();
    $conn = $table->establishConnection('r');

    $our_versions = queryfx_all(
      $conn,
      'SELECT repositoryPHID, repositoryVersion FROM %R WHERE devicePHID = %s',
      $table,
      $device->getPHID());
    $our_versions = ipull($our_versions, 'repositoryVersion', 'repositoryPHID');

    $max_versions = queryfx_all(
      $conn,
      'SELECT repositoryPHID, MAX(repositoryVersion) maxVersion FROM %R
        GROUP BY repositoryPHID',
      $table);
    $max_versions = ipull($max_versions, 'maxVersion', 'repositoryPHID');

    $unsynchronized_phids = array();
    foreach ($max_versions as $repository_phid => $max_version) {
      $our_version = idx($our_versions, $repository_phid);
      if (($our_version === null) || ($our_version < $max_version)) {
        $unsynchronized_phids[] = $repository_phid;
      }
    }

    if (!$unsynchronized_phids) {
      return array();
    }

    return id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs($unsynchronized_phids)
      ->execute();
  }

}
