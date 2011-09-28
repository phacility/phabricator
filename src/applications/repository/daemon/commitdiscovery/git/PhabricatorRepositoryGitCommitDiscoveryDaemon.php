<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorRepositoryGitCommitDiscoveryDaemon
  extends PhabricatorRepositoryCommitDiscoveryDaemon {

  protected function discoverCommits() {
    // NOTE: PhabricatorRepositoryGitFetchDaemon does the actual pulls, this
    // just parses HEAD.

    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_GIT) {
      throw new Exception("Repository is not a git repository.");
    }

    $repository_phid = $repository->getPHID();

    list($stdout) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev');

    $branches = DiffusionGitBranchQuery::parseGitRemoteBranchOutput($stdout);

    $got_something = false;
    foreach ($branches as $name => $commit) {
      if ($this->isKnownCommit($commit)) {
        continue;
      } else {
        $this->discoverCommit($commit);
        $got_something = true;
      }
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

}
