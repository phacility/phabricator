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

  private $lastCommit;
  private $commitCache = array();

  protected function discoverCommits() {
    // NOTE: PhabricatorRepositoryGitPullDaemon does the actual pulls, this
    // just parses HEAD.

    $repository = $this->getRepository();

    // TODO: this should be a constant somewhere
    if ($repository->getVersionControlSystem() != 'git') {
      throw new Exception("Repository is not a git repository.");
    }

    $repository_phid = $repository->getPHID();

    $repo_base = $repository->getDetail('local-path');
    list($commit) = execx(
      '(cd %s && git log -n1 --pretty="%%H")',
      $repo_base);
    $commit = trim($commit);

    if ($commit === $this->lastCommit ||
        $this->isKnownCommit($commit)) {
      return false;
    }

    $this->lastCommit = $commit;
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
          echo "{$target} has parent {$parent}\n";
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
      list($epoch) = execx(
        '(cd %s && git log -n1 --pretty="%%at" %s)',
        $repo_base,
        $target);
      $epoch = trim($epoch);

      $commit = new PhabricatorRepositoryCommit();
      $commit->setRepositoryPHID($this->getRepository()->getPHID());
      $commit->setCommitIdentifier($target);
      $commit->setEpoch($epoch);
      try {
        $commit->save();
        $event = new PhabricatorTimelineEvent(
          'cmit',
          array(
            'id' => $commit->getID(),
          ));
        $event->recordEvent();
      } catch (AphrontQueryDuplicateKeyException $ex) {
        // Ignore. This can happen because we discover the same new commit
        // more than once when looking at history, or because of races or
        // data inconsistency or cosmic radiation; in any case, we're still
        // in a good state if we ignore the failure.
      }
      if (empty($insert)) {
        break;
      }
    }
  }

  private function isKnownCommit($target) {
    if (isset($this->commitCache[$target])) {
      return true;
    }

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryPHID = %s AND commitIdentifier = %s',
      $this->getRepository()->getPHID(),
      $target);

    if (!$commit) {
      return false;
    }

    $this->commitCache[$target] = true;
    if (count($this->commitCache) > 16) {
      array_shift($this->commitCache);
    }

    return true;
  }

}
