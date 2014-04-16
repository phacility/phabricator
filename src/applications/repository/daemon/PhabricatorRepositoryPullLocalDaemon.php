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
 */
final class PhabricatorRepositoryPullLocalDaemon
  extends PhabricatorDaemon {


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
    $include = $args->getArg('repositories');
    $exclude = $args->getArg('not');

    // Each repository has an individual pull frequency; after we pull it,
    // wait that long to pull it again. When we start up, try to pull everything
    // serially.
    $retry_after = array();

    $min_sleep = 15;
    $max_futures = 4;
    $futures = array();
    $queue = array();

    while (true) {
      $pullable = $this->loadPullableRepositories($include, $exclude);

      // If any repositories have the NEEDS_UPDATE flag set, pull them
      // as soon as possible.
      $need_update_messages = $this->loadRepositoryUpdateMessages();
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

      // If any repositories were deleted, remove them from the retry timer map
      // so we don't end up with a retry timer that never gets updated and
      // causes us to sleep for the minimum amount of time.
      $retry_after = array_select_keys(
        $retry_after,
        array_keys($pullable));


      // Figure out which repositories we need to queue for an update.
      foreach ($pullable as $id => $repository) {
        $monogram = $repository->getMonogram();

        if (isset($futures[$id])) {
          $this->log(pht('Repository "%s" is currently updating.', $monogram));
          continue;
        }

        if (isset($queue[$id])) {
          $this->log(pht('Repository "%s" is already queued.', $monogram));
          continue;
        }

        $after = idx($retry_after, $id, 0);
        if ($after > time()) {
          $this->log(
            pht(
              'Repository "%s" is not due for an update for %s second(s).',
              $monogram,
              new PhutilNumber($after - time())));
          continue;
        }

        if (!$after) {
          $this->log(
            pht(
              'Scheduling repository "%s" for an initial update.',
              $monogram));
        } else {
          $this->log(
            pht(
              'Scheduling repository "%s" for an update (%s seconds overdue).',
              $monogram,
              new PhutilNumber(time() - $after)));
        }

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
            break;
          }

          $monogram = $repository->getMonogram();
          $this->log(pht('Starting update for repository "%s".', $monogram));

          unset($queue[$id]);
          $futures[$id] = $this->buildUpdateFuture(
            $repository,
            $no_discovery);

          break;
        }
      }

      if ($queue) {
        $this->log(
          pht(
            'Not enough process slots to schedule the other %s '.
            'repository(s) for updates yet.',
            new PhutilNumber(count($queue))));
      }

      if ($futures) {
        $iterator = id(new FutureIterator($futures))
          ->setUpdateInterval($min_sleep);

        foreach ($iterator as $id => $future) {
          $this->stillWorking();

          if ($future === null) {
            $this->log(pht('Waiting for updates to complete...'));
            $this->stillWorking();

            if ($this->loadRepositoryUpdateMessages()) {
              $this->log(pht('Interrupted by pending updates!'));
              break;
            }

            continue;
          }

          unset($futures[$id]);
          $retry_after[$id] = $this->resolveUpdateFuture(
            $pullable[$id],
            $future,
            $min_sleep);

          // We have a free slot now, so go try to fill it.
          break;
        }

        // Jump back into prioritization if we had any futures to deal with.
        continue;
      }

      $this->waitForUpdates($min_sleep, $retry_after);
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

    $callsign = $repository->getCallsign();

    $future = new ExecFuture('%s update %Ls -- %s', $bin, $flags, $callsign);

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

    return $future;
  }


  /**
   * @task pull
   */
  private function loadRepositoryUpdateMessages() {
    $type_need_update = PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE;
    return id(new PhabricatorRepositoryStatusMessage())
      ->loadAllWhere('statusType = %s', $type_need_update);
  }


  /**
   * @task pull
   */
  private function loadPullableRepositories(array $include, array $exclude) {
    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer());

    if ($include) {
      $query->withCallsigns($include);
    }

    $repositories = $query->execute();

    if ($include) {
      $by_callsign = mpull($repositories, null, 'getCallsign');
      foreach ($include as $name) {
        if (empty($by_callsign[$name])) {
          throw new Exception(
            "No repository exists with callsign '{$name}'!");
        }
      }
    }

    if ($exclude) {
      $exclude = array_fuse($exclude);
      foreach ($repositories as $key => $repository) {
        if (isset($exclude[$repository->getCallsign()])) {
          unset($repositories[$key]);
        }
      }
    }

    foreach ($repositories as $key => $repository) {
      if (!$repository->isTracked()) {
        unset($repositories[$key]);
      }
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

    $monogram = $repository->getMonogram();

    $this->log(pht('Resolving update for "%s".', $monogram));

    try {
      list($stdout, $stderr) = $future->resolvex();
    } catch (Exception $ex) {
      $proxy = new PhutilProxyException(
        pht(
          'Error while updating the "%s" repository.',
          $repository->getMonogram()),
        $ex);
      phlog($proxy);

      return time() + $min_sleep;
    }

    if (strlen($stderr)) {
      $stderr_msg = pht(
        'Unexpected output while updating repository "%s": %s',
        $monogram,
        $stderr);
      phlog($stderr_msg);
    }

    $sleep_for = (int)$repository->getDetail('pull-frequency', $min_sleep);

    // Smart wait: pull rarely used repositories less frequently. Find the
    // most recent commit which is older than the current time (this keeps us
    // from spinning on repositories with a silly commit post-dated to some time
    // in 2037), and adjust how frequently we pull based on how frequently this
    // repository updates.

    $table = id(new PhabricatorRepositoryCommit());
    $last_commit = queryfx_one(
      $table->establishConnection('w'),
      'SELECT epoch FROM %T
        WHERE repositoryID = %d AND epoch <= %d
        ORDER BY epoch DESC LIMIT 1',
      $table->getTableName(),
      $repository->getID(),
      time() + $min_sleep);
    if ($last_commit) {
      $time_since_commit = (time() + $min_sleep) - $last_commit['epoch'];

      // Wait 0.5% of the time since the last commit before we pull. This gives
      // us these wait times:
      //
      // 50 minutes or less: 15 seconds
      // about 3 hours: 1 minute
      // about 16 hours: 5 minutes
      // about 2 days: 15 minutes
      // 50 days or more: 6 hours

      $smart_wait = ($time_since_commit / 200);
      $smart_wait = min($smart_wait, phutil_units('6 hours in seconds'));

      $this->log(
        pht(
          'Last commit to repository "%s" was %s seconds ago; considering '.
          'a wait of %s seconds before update.',
          $repository->getMonogram(),
          new PhutilNumber($time_since_commit),
          new PhutilNumber($smart_wait)));

      $smart_wait = max(15, $smart_wait);
      $sleep_for = max($smart_wait, $sleep_for);
    }

    if ($sleep_for < $min_sleep) {
      $sleep_for = $min_sleep;
    }

    return time() + $sleep_for;
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

      $this->log(
        pht(
          'Sleeping for %s more second(s)...',
          new PhutilNumber($sleep_duration)));

      $this->sleep(1);
      if ($this->loadRepositoryUpdateMessages()) {
        $this->log(pht('Awakened from sleep by pending updates!'));
        break;
      }
    }
  }

}
