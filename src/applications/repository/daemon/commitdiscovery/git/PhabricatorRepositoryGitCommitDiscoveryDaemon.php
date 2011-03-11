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
    // NOTE: PhabricatorRepositoryGitPullDaemon does the actual pulls, this
    // just parses HEAD.

    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_GIT) {
      throw new Exception("Repository is not a git repository.");
    }

    $repository_phid = $repository->getPHID();

    $repo_base = $repository->getDetail('local-path');
    list($commit) = execx(
      '(cd %s && git log -n1 --pretty="%%H")',
      $repo_base);
    $commit = trim($commit);

    if ($this->isKnownCommit($commit)) {
      return false;
    }

    $this->discoverCommit($commit);

    return true;
  }

  private function discoverCommit($commit) {
    $discover = array();
    $insert = array();

    $repository = $this->getRepository();
    $repo_base = $repository->getDetail('local-path');

    $discover[] = $commit;
    $insert[] = $commit;

    while (true) {
      $target = array_pop($discover);
      list($parents) = execx(
        '(cd %s && git log -n1 --pretty="%%P" %s)',
        $repo_base,
        $target);
      $parents = array_filter(explode(' ', trim($parents)));
      foreach ($parents as $parent) {
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
      list($epoch) = execx(
        '(cd %s && git log -n1 --pretty="%%at" %s)',
        $repo_base,
        $target);
      $epoch = trim($epoch);

      $this->recordCommit($target, $epoch);

      if (empty($insert)) {
        break;
      }
    }
  }

}
