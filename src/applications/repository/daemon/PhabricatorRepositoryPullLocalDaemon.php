<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

        try {
          $callsign = $repository->getCallsign();
          $this->log("Updating repository '{$callsign}'.");

          $this->pullRepository($repository);

          if (!$no_discovery) {
            // TODO: It would be nice to discover only if we pulled something,
            // but this isn't totally trivial.

            $lock_name = get_class($this).':'.$callsign;
            $lock = PhabricatorGlobalLock::newLock($lock_name);
            $lock->lock();

            try {
              $this->discoverRepository($repository);
            } catch (Exception $ex) {
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
          phlog($ex);
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
    if (!count($names)) {
      return id(new PhabricatorRepository())->loadAll();
    } else {
      return PhabricatorRepository::loadAllByPHIDOrCallsign($names);
    }
  }


  /**
   * @task pull
   */
  public function pullRepository(PhabricatorRepository $repository) {
    $vcs = $repository->getVersionControlSystem();

    $is_svn = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_SVN);
    $is_git = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);
    $is_hg = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL);

    if ($is_svn) {
      return;
    }

    $callsign = $repository->getCallsign();

    if (!$is_git && !$is_hg) {
      throw new Exception(
        "Unknown VCS '{$vcs}' for repository '{$callsign}'!");
    }

    $local_path = $repository->getDetail('local-path');
    if (!$local_path) {
      throw new Exception(
        "No local path is available for repository '{$callsign}'.");
    }

    if (!Filesystem::pathExists($local_path)) {
      $dirname = dirname($local_path);
      if (!Filesystem::pathExists($dirname)) {
        Filesystem::createDirectory($dirname, 0755, $recursive = true);
      }

      if ($is_git) {
        return $this->executeGitCreate($repository, $local_path);
      } else if ($is_hg) {
        return $this->executeHgCreate($repository, $local_path);
      }
    } else {
      if ($is_git) {
        return $this->executeGitUpdate($repository, $local_path);
      } else if ($is_hg) {
        return $this->executeHgUpdate($repository, $local_path);
      }
    }
  }

  public function discoverRepository(PhabricatorRepository $repository) {
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        return $this->executeGitDiscover($repository);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return $this->executeSvnDiscover($repository);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return $this->executeHgDiscover($repository);
      default:
        throw new Exception("Unknown VCS '{$vcs}'!");
    }
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
      'repositoryID = %s AND commitIdentifier = %s',
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
      'repositoryID = %s AND commitIdentifier = %s',
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

      $event = new PhabricatorTimelineEvent(
        'cmit',
        array(
          'id' => $commit->getID(),
        ));
      $event->recordEvent();

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
      'repositoryID = %s AND commitIdentifier = %s',
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



/* -(  Git Implementation  )------------------------------------------------- */


  /**
   * @task git
   */
  private function executeGitCreate(
    PhabricatorRepository $repository,
    $path) {

    $repository->execxRemoteCommand(
      'clone --origin origin %s %s',
      $repository->getRemoteURI(),
      rtrim($path, '/'));
  }


  /**
   * @task git
   */
  private function executeGitUpdate(
    PhabricatorRepository $repository,
    $path) {

    // Run a bunch of sanity checks to detect people checking out repositories
    // inside other repositories, making empty directories, pointing the local
    // path at some random file or path, etc.

    list($err, $stdout) = $repository->execLocalCommand(
      'rev-parse --show-toplevel');

    if ($err) {

      // Try to raise a more tailored error message in the more common case
      // of the user creating an empty directory. (We could try to remove it,
      // but might not be able to, and it's much simpler to raise a good
      // message than try to navigate those waters.)
      if (is_dir($path)) {
        $files = Filesystem::listDirectory($path, $include_hidden = true);
        if (!$files) {
          throw new Exception(
            "Expected to find a git repository at '{$path}', but there ".
            "is an empty directory there. Remove the directory: the daemon ".
            "will run 'git clone' for you.");
        }
      }

      throw new Exception(
        "Expected to find a git repository at '{$path}', but there is ".
        "a non-repository directory (with other stuff in it) there. Move or ".
        "remove this directory (or reconfigure the repository to use a ".
        "different directory), and then either clone a repository yourself ".
        "or let the daemon do it.");
    } else {
      $repo_path = rtrim($stdout, "\n");

      if (empty($repo_path)) {
        throw new Exception(
          "Expected to find a git repository at '{$path}', but ".
          "there was no result from `git rev-parse --show-toplevel`. ".
          "Something is misconfigured or broken. The git repository ".
          "may be inside a '.git/' directory.");
      }

      if (!Filesystem::pathsAreEquivalent($repo_path, $path)) {
        throw new Exception(
          "Expected to find repo at '{$path}', but the actual ".
          "git repository root for this directory is '{$repo_path}'. ".
          "Something is misconfigured. The repository's 'Local Path' should ".
          "be set to some place where the daemon can check out a working ".
          "copy, and should not be inside another git repository.");
      }
    }


    // This is a local command, but needs credentials.
    $future = $repository->getRemoteCommandFuture('fetch --all --prune');
    $future->setCWD($path);
    $future->resolvex();
  }


  /**
   * @task git
   */
  private function executeGitDiscover(
    PhabricatorRepository $repository) {

    list($remotes) = $repository->execxLocalCommand(
      'remote show -n origin');

    $matches = null;
    if (!preg_match('/^\s*Fetch URL:\s*(.*?)\s*$/m', $remotes, $matches)) {
      throw new Exception(
        "Expected 'Fetch URL' in 'git remote show -n origin'.");
    }

    self::executeGitVerifySameOrigin(
      $matches[1],
      $repository->getRemoteURI(),
      $repository->getLocalPath());

    list($stdout) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev');

    $branches = DiffusionGitBranchQuery::parseGitRemoteBranchOutput(
      $stdout,
      $only_this_remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE);

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
   * @task git
   */
  public static function executeGitVerifySameOrigin($remote, $expect, $where) {
    $remote_path = self::getPathFromGitURI($remote);
    $expect_path = self::getPathFromGitURI($expect);

    $remote_match = self::executeGitNormalizePath($remote_path);
    $expect_match = self::executeGitNormalizePath($expect_path);

    if ($remote_match != $expect_match) {
      throw new Exception(
        "Working copy at '{$where}' has a mismatched origin URL. It has ".
        "origin URL '{$remote}' (with remote path '{$remote_path}'), but the ".
        "configured URL '{$expect}' (with remote path '{$expect_path}') is ".
        "expected. Refusing to proceed because this may indicate that the ".
        "working copy is actually some other repository.");
    }
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


/* -(  Mercurial Implementation  )------------------------------------------- */


  /**
   * @task hg
   */
  private function executeHgCreate(
    PhabricatorRepository $repository,
    $path) {

    $repository->execxRemoteCommand(
      'clone %s %s',
      $repository->getRemoteURI(),
      rtrim($path, '/'));
  }


  /**
   * @task hg
   */
  private function executeHgUpdate(
    PhabricatorRepository $repository,
    $path) {

    // This is a local command, but needs credentials.
    $future = $repository->getRemoteCommandFuture('pull -u');
    $future->setCWD($path);

    try {
      $future->resolvex();
    } catch (CommandException $ex) {
      $err = $ex->getError();
      $stdout = $ex->getStdOut();

      // NOTE: Between versions 2.1 and 2.1.1, Mercurial changed the behavior
      // of "hg pull" to return 1 in case of a successful pull with no changes.
      // This behavior has been reverted, but users who updated between Feb 1,
      // 2012 and Mar 1, 2012 will have the erroring version. Do a dumb test
      // against stdout to check for this possibility.
      // See: https://github.com/facebook/phabricator/issues/101/

      // NOTE: Mercurial has translated versions, which translate this error
      // string. In a translated version, the string will be something else,
      // like "aucun changement trouve". There didn't seem to be an easy way
      // to handle this (there are hard ways but this is not a common problem
      // and only creates log spam, not application failures). Assume English.

      // TODO: Remove this once we're far enough in the future that deployment
      // of 2.1 is exceedingly rare?
      if ($err == 1 && preg_match('/no changes found/', $stdout)) {
        return;
      } else {
        throw $ex;
      }
    }
  }

  private function executeHgDiscover(PhabricatorRepository $repository) {
    // NOTE: "--debug" gives us 40-character hashes.
    list($stdout) = $repository->execxLocalCommand('--debug branches');

    $branches = ArcanistMercurialParser::parseMercurialBranches($stdout);
    $got_something = false;
    foreach ($branches as $name => $branch) {
      $commit = $branch['rev'];
      if ($this->isKnownCommit($repository, $commit)) {
        continue;
      } else {
        $this->executeHgDiscoverCommit($repository, $commit);
        $got_something = true;
      }
    }

    return $got_something;
  }

  private function executeHgDiscoverCommit(
    PhabricatorRepository $repository,
    $commit) {

    $discover = array($commit);
    $insert = array($commit);

    $seen_parent = array();

    $stream = new PhabricatorMercurialGraphStream($repository);

    // For all the new commits at the branch heads, walk backward until we
    // find only commits we've aleady seen.
    while ($discover) {
      $target = array_pop($discover);

      $parents = $stream->getParents($target);

      foreach ($parents as $parent) {
        if (isset($seen_parent[$parent])) {
          continue;
        }
        $seen_parent[$parent] = true;
        if (!$this->isKnownCommit($repository, $parent)) {
          $discover[] = $parent;
          $insert[] = $parent;
        }
      }
    }

    foreach ($insert as $target) {
      $epoch = $stream->getCommitDate($target);
      $this->recordCommit($repository, $target, $epoch);
    }
  }


/* -(  Subversion Implementation  )------------------------------------------ */


  private function executeSvnDiscover(
    PhabricatorRepository $repository) {

    $uri = $this->executeSvnGetBaseSVNLogURI($repository);

    list($xml) = $repository->execxRemoteCommand(
      'log --xml --quiet --limit 1 %s@HEAD',
      $uri);

    $results = $this->executeSvnParseLogXML($xml);
    $commit = head_key($results);
    $epoch  = head($results);

    if ($this->isKnownCommit($repository, $commit)) {
      return false;
    }

    $this->executeSvnDiscoverCommit($repository, $commit, $epoch);
    return true;
  }

  private function executeSvnDiscoverCommit(
    PhabricatorRepository $repository,
    $commit,
    $epoch) {

    $uri = $this->executeSvnGetBaseSVNLogURI($repository);

    $discover = array(
      $commit => $epoch,
    );
    $upper_bound = $commit;

    $limit = 1;
    while ($upper_bound > 1 &&
           !$this->isKnownCommit($repository, $upper_bound)) {
      // Find all the unknown commits on this path. Note that we permit
      // importing an SVN subdirectory rather than the entire repository, so
      // commits may be nonsequential.
      list($err, $xml, $stderr) = $repository->execRemoteCommand(
        ' log --xml --quiet --limit %d %s@%d',
        $limit,
        $uri,
        $upper_bound - 1);
      if ($err) {
        if (preg_match('/(path|File) not found/', $stderr)) {
          // We've gone all the way back through history and this path was not
          // affected by earlier commits.
          break;
        } else {
          throw new Exception("svn log error #{$err}: {$stderr}");
        }
      }
      $discover += $this->executeSvnParseLogXML($xml);

      $upper_bound = min(array_keys($discover));

      // Discover 2, 4, 8, ... 256 logs at a time. This allows us to initially
      // import large repositories fairly quickly, while pulling only as much
      // data as we need in the common case (when we've already imported the
      // repository and are just grabbing one commit at a time).
      $limit = min($limit * 2, 256);
    }

    // NOTE: We do writes only after discovering all the commits so that we're
    // never left in a state where we've missed commits -- if the discovery
    // script terminates it can always resume and restore the import to a good
    // state. This is also why we sort the discovered commits so we can do
    // writes forward from the smallest one.

    ksort($discover);
    foreach ($discover as $commit => $epoch) {
      $this->recordCommit($repository, $commit, $epoch);
    }
  }

  private function executeSvnParseLogXML($xml) {
    $xml = phutil_utf8ize($xml);

    $result = array();

    $log = new SimpleXMLElement($xml);
    foreach ($log->logentry as $entry) {
      $commit = (int)$entry['revision'];
      $epoch  = (int)strtotime((string)$entry->date[0]);
      $result[$commit] = $epoch;
    }

    return $result;
  }


  private function executeSvnGetBaseSVNLogURI(
    PhabricatorRepository $repository) {

    $uri = $repository->getDetail('remote-uri');
    $subpath = $repository->getDetail('svn-subpath');

    return $uri.$subpath;
  }

}
