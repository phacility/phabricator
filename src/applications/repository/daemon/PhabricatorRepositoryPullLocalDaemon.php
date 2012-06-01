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

  private static $commitCache = array();


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
    $repo_names     = $args->getArg('repositories', array());
    $exclude_names  = $args->getArg('not', array());

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
          self::pullRepository($repository);

          if (!$no_discovery) {
            // TODO: It would be nice to discover only if we pulled something,
            // but this isn't totally trivial.
            self::discoverRepository($repository);
          }

          $sleep_for = $repository->getDetail('pull-frequency', $min_sleep);
          $retry_after[$id] = time() + $sleep_for;
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
  public static function pullRepository(PhabricatorRepository $repository) {
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
        echo "Creating new directory '{$dirname}' ".
             "for repository '{$callsign}'.\n";
        Filesystem::createDirectory($dirname, 0755, $recursive = true);
      }

      if ($is_git) {
        return self::executeGitCreate($repository, $local_path);
      } else if ($is_hg) {
        return self::executeHgCreate($repository, $local_path);
      }
    } else {
      if ($is_git) {
        return self::executeGitUpdate($repository, $local_path);
      } else if ($is_hg) {
        return self::executeHgUpdate($repository, $local_path);
      }
    }
  }

  public static function discoverRepository(PhabricatorRepository $repository) {
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        return self::executeGitDiscover($repository);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return self::executeSvnDiscover($repository);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return self::executeHgDiscover($repository);
      default:
        throw new Exception("Unknown VCS '{$vcs}'!");
    }
  }


  private static function isKnownCommit(
    PhabricatorRepository $repository,
    $target) {

    if (self::getCache($repository, $target)) {
      return true;
    }

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %s AND commitIdentifier = %s',
      $repository->getID(),
      $target);

    if (!$commit) {
      return false;
    }

    self::setCache($repository, $target);
    while (count(self::$commitCache) > 2048) {
      array_shift(self::$commitCache);
    }

    return true;
  }

  private static function recordCommit(
    PhabricatorRepository $repository,
    $commit_identifier,
    $epoch) {

    $commit = new PhabricatorRepositoryCommit();
    $commit->setRepositoryID($repository->getID());
    $commit->setCommitIdentifier($commit_identifier);
    $commit->setEpoch($epoch);

    try {
      $commit->save();

      $event = new PhabricatorTimelineEvent(
        'cmit',
        array(
          'id' => $commit->getID(),
        ));
      $event->recordEvent();

      self::insertTask($repository, $commit);

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

      self::setCache($repository, $commit_identifier);
    } catch (AphrontQueryDuplicateKeyException $ex) {
      // Ignore. This can happen because we discover the same new commit
      // more than once when looking at history, or because of races or
      // data inconsistency or cosmic radiation; in any case, we're still
      // in a good state if we ignore the failure.
      self::setCache($repository, $commit_identifier);
    }
  }

  private static function insertTask(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

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

    $task = new PhabricatorWorkerTask();
    $task->setTaskClass($class);
    $task->setData(
      array(
        'commitID' => $commit->getID(),
      ));
    $task->save();
  }


  private static function setCache(
    PhabricatorRepository $repository,
    $commit_identifier) {

    $key = self::getCacheKey($repository, $commit_identifier);
    self::$commitCache[$key] = true;
  }

  private static function getCache(
    PhabricatorRepository $repository,
    $commit_identifier) {

    $key = self::getCacheKey($repository, $commit_identifier);
    return idx(self::$commitCache, $key, false);
  }

  private static function getCacheKey(
    PhabricatorRepository $repository,
    $commit_identifier) {

    return $repository->getID().':'.$commit_identifier;
  }



/* -(  Git Implementation  )------------------------------------------------- */


  /**
   * @task git
   */
  private static function executeGitCreate(
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
  private static function executeGitUpdate(
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
  private static function executeGitDiscover(
    PhabricatorRepository $repository) {

    list($remotes) = $repository->execxLocalCommand(
      'remote show -n origin');

    $matches = null;
    if (!preg_match('/^\s*Fetch URL:\s*(.*?)\s*$/m', $remotes, $matches)) {
      throw new Exception(
        "Expected 'Fetch URL' in 'git remote show -n origin'.");
    }

    self::executeGitverifySameOrigin(
      $matches[1],
      $repository->getRemoteURI(),
      $repository->getLocalPath());

    list($stdout) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev');

    $branches = DiffusionGitBranchQuery::parseGitRemoteBranchOutput(
      $stdout,
      $only_this_remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE);

    $tracked_something = false;
    foreach ($branches as $name => $commit) {
      if (!$repository->shouldTrackBranch($name)) {
        continue;
      }

      $tracked_something = true;

      if (self::isKnownCommit($repository, $commit)) {
        continue;
      } else {
        self::executeGitDiscoverCommit($repository, $commit);
      }
    }

    if (!$tracked_something) {
      $repo_name = $repository->getName();
      $repo_callsign = $repository->getCallsign();
      throw new Exception(
        "Repository r{$repo_callsign} '{$repo_name}' has no tracked branches! ".
        "Verify that your branch filtering settings are correct.");
    }
  }


  /**
   * @task git
   */
  private static function executeGitDiscoverCommit(
    PhabricatorRepository $repository,
    $commit) {

    $discover = array($commit);
    $insert = array($commit);

    $seen_parent = array();

    while (true) {
      $target = array_pop($discover);
      list($parents) = $repository->execxLocalCommand(
        'log -n1 --pretty="%%P" %s',
        $target);
      $parents = array_filter(explode(' ', trim($parents)));
      foreach ($parents as $parent) {
        if (isset($seen_parent[$parent])) {
          // We end up in a loop here somehow when we parse Arcanist if we
          // don't do this. TODO: Figure out why and draw a pretty diagram
          // since it's not evident how parsing a DAG with this causes the
          // loop to stop terminating.
          continue;
        }
        $seen_parent[$parent] = true;
        if (!self::isKnownCommit($repository, $parent)) {
          $discover[] = $parent;
          $insert[] = $parent;
        }
      }
      if (empty($discover)) {
        break;
      }
    }

    while (true) {
      $target = array_pop($insert);
      list($epoch) = $repository->execxLocalCommand(
        'log -n1 --pretty="%%ct" %s',
        $target);
      $epoch = trim($epoch);

      self::recordCommit($repository, $target, $epoch);

      if (empty($insert)) {
        break;
      }
    }
  }


  /**
   * @task git
   */
  public static function executeGitVerifySameOrigin($remote, $expect, $where) {
    $remote_uri = PhabricatorRepository::newPhutilURIFromGitURI($remote);
    $expect_uri = PhabricatorRepository::newPhutilURIFromGitURI($expect);

    $remote_path = $remote_uri->getPath();
    $expect_path = $expect_uri->getPath();

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


  /**
   * @task git
   */
  private static function executeGitNormalizePath($path) {
    // Strip away trailing "/" and ".git", so similar paths correctly match.

    $path = rtrim($path, '/');
    $path = preg_replace('/\.git$/', '', $path);
    return $path;
  }


/* -(  Mercurial Implementation  )------------------------------------------- */


  /**
   * @task hg
   */
  private static function executeHgCreate(
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
  private static function executeHgUpdate(
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

  private static function executeHgDiscover(PhabricatorRepository $repository) {
    // NOTE: "--debug" gives us 40-character hashes.
    list($stdout) = $repository->execxLocalCommand('--debug branches');

    $branches = ArcanistMercurialParser::parseMercurialBranches($stdout);
    $got_something = false;
    foreach ($branches as $name => $branch) {
      $commit = $branch['rev'];
      if (self::isKnownCommit($repository, $commit)) {
        continue;
      } else {
        self::executeHgDiscoverCommit($repository, $commit);
        $got_something = true;
      }
    }

    return $got_something;
  }

  private static function executeHgDiscoverCommit(
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
        if (!self::isKnownCommit($repository, $parent)) {
          $discover[] = $parent;
          $insert[] = $parent;
        }
      }
    }

    foreach ($insert as $target) {
      $epoch = $stream->getCommitDate($target);
      self::recordCommit($repository, $target, $epoch);
    }
  }


/* -(  Subversion Implementation  )------------------------------------------ */


  private static function executeSvnDiscover(
    PhabricatorRepository $repository) {

    $uri = self::executeSvnGetBaseSVNLogURI($repository);

    list($xml) = $repository->execxRemoteCommand(
      'log --xml --quiet --limit 1 %s@HEAD',
      $uri);

    $results = self::executeSvnParseLogXML($xml);
    $commit = head_key($results);
    $epoch  = head($results);

    if (self::isKnownCommit($repository, $commit)) {
      return false;
    }

    self::executeSvnDiscoverCommit($repository, $commit, $epoch);
    return true;
  }

  private static function executeSvnDiscoverCommit(
    PhabricatorRepository $repository,
    $commit,
    $epoch) {

    $uri = self::executeSvnGetBaseSVNLogURI($repository);

    $discover = array(
      $commit => $epoch,
    );
    $upper_bound = $commit;

    $limit = 1;
    while ($upper_bound > 1 &&
           !self::isKnownCommit($repository, $upper_bound)) {
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
      $discover += self::executeSvnParseLogXML($xml);

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
      self::recordCommit($repository, $commit, $epoch);
    }
  }

  private static function executeSvnParseLogXML($xml) {
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


  private static function executeSvnGetBaseSVNLogURI(
    PhabricatorRepository $repository) {

    $uri = $repository->getDetail('remote-uri');
    $subpath = $repository->getDetail('svn-subpath');

    return $uri.$subpath;
  }

}
