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

final class PhabricatorRepositoryGitCommitDiscoveryDaemon
  extends PhabricatorRepositoryCommitDiscoveryDaemon {

  protected function discoverCommits() {
    // NOTE: PhabricatorRepositoryGitFetchDaemon does the actual pulls, this
    // just parses HEAD.

    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_GIT) {
      throw new Exception("Repository is not a git repository.");
    }

    list($remotes) = $repository->execxLocalCommand(
      'remote show -n origin');

    $matches = null;
    if (!preg_match('/^\s*Fetch URL:\s*(.*?)\s*$/m', $remotes, $matches)) {
      throw new Exception(
        "Expected 'Fetch URL' in 'git remote show -n origin'.");
    }

    self::verifySameGitOrigin(
      $matches[1],
      $repository->getRemoteURI(),
      $repository->getLocalPath());

    list($stdout) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev');

    $branches = DiffusionGitBranchQuery::parseGitRemoteBranchOutput(
      $stdout,
      $only_this_remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE);

    $got_something = false;
    $tracked_something = false;
    foreach ($branches as $name => $commit) {
      if (!$repository->shouldTrackBranch($name)) {
        continue;
      }

      $tracked_something = true;

      if ($this->isKnownCommit($commit)) {
        continue;
      } else {
        $this->discoverCommit($commit);
        $got_something = true;
      }
    }

    if (!$tracked_something) {
      $repo_name = $repository->getName();
      $repo_callsign = $repository->getCallsign();
      throw new Exception(
        "Repository r{$repo_callsign} '{$repo_name}' has no tracked branches! ".
        "Verify that your branch filtering settings are correct.");
    }

    return $got_something;
  }

  private function discoverCommit($commit) {
    $discover = array();
    $insert = array();

    $repository = $this->getRepository();

    $discover[] = $commit;
    $insert[] = $commit;

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
        if (!$this->isKnownCommit($parent)) {
          $discover[] = $parent;
          $insert[] = $parent;
        }
      }
      if (empty($discover)) {
        break;
      }
      $this->stillWorking();
    }

    while (true) {
      $target = array_pop($insert);
      list($epoch) = $repository->execxLocalCommand(
        'log -n1 --pretty="%%at" %s',
        $target);
      $epoch = trim($epoch);

      $this->recordCommit($target, $epoch);

      if (empty($insert)) {
        break;
      }
    }
  }

  public static function verifySameGitOrigin($remote, $expect, $where) {
    $remote_uri = PhabricatorRepository::newPhutilURIFromGitURI($remote);
    $expect_uri = PhabricatorRepository::newPhutilURIFromGitURI($expect);

    $remote_path = $remote_uri->getPath();
    $expect_path = $expect_uri->getPath();

    $remote_match = self::normalizeGitPath($remote_path);
    $expect_match = self::normalizeGitPath($expect_path);

    if ($remote_match != $expect_match) {
      throw new Exception(
        "Working copy at '{$where}' has a mismatched origin URL. It has ".
        "origin URL '{$remote}' (with remote path '{$remote_path}'), but the ".
        "configured URL '{$expect}' (with remote path '{$expect_path}') is ".
        "expected. Refusing to proceed because this may indicate that the ".
        "working copy is actually some other repository.");
    }
  }

  private static function normalizeGitPath($path) {
    // Strip away trailing "/" and ".git", so similar paths correctly match.

    $path = rtrim($path, '/');
    $path = preg_replace('/\.git$/', '', $path);
    return $path;
  }

}
