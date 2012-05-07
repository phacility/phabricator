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
 *   ./phd launch repositorypulllocal X Q Z
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


/* -(  Pulling Repositories  )----------------------------------------------- */


  /**
   * @task pull
   */
  public function run() {

    // Each repository has an individual pull frequency; after we pull it,
    // wait that long to pull it again. When we start up, try to pull everything
    // serially.
    $retry_after = array();

    while (true) {
      $repositories = $this->loadRepositories();

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
        if ($after >= time()) {
          continue;
        }

        try {
          self::pullRepository($repository);
          $sleep_for = $repository->getDetail('pull-frequency', 15);
          $retry_after[$id] = time() + $sleep_for;
        } catch (Exception $ex) {
          $retry_after[$id] = time() + 15;
          phlog($ex);
        }
      }

      $sleep_until = max(min($retry_after), time() + 15);
      sleep($sleep_until - time());
    }
  }


  /**
   * @task pull
   */
  protected function loadRepositories() {
    $argv = $this->getArgv();
    if (!count($argv)) {
      return id(new PhabricatorRepository())->loadAll();
    } else {
      return PhabricatorRepository::loadAllByPHIDOrCallsign($argv);
    }
  }


  /**
   * @task pull
   */
  public static function pullRepository(PhabricatorRepository $repository) {
    $tracked = $repository->isTracked();
    if (!$tracked) {
      return;
    }

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
        self::executeGitCreate($repository, $local_path);
      } else if ($is_hg) {
        self::executeHgCreate($repository, $local_path);
      }
    } else {
      if ($is_git) {
        self::executeGitUpdate($repository, $local_path);
      } else if ($is_hg) {
        self::executeHgUpdate($repository, $local_path);
      }
    }
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

}
