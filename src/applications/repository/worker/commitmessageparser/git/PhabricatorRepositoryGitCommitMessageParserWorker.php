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

final class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    // NOTE: %B was introduced somewhat recently in git's history, so pull
    // commit message information with %s and %b instead.
    list($info) = $repository->execxLocalCommand(
      "log -n 1 --encoding='UTF-8' --pretty=format:%%an%%x00%%s%%n%%n%%b %s",
      $commit->getCommitIdentifier());

    list($author, $message) = explode("\0", $info);

    // Make sure these are valid UTF-8.
    $author = phutil_utf8ize($author);
    $message = phutil_utf8ize($message);
    $message = trim($message);

    $this->updateCommitData($author, $message);

    if ($this->shouldQueueFollowupTasks()) {
      $task = new PhabricatorWorkerTask();
      $task->setTaskClass('PhabricatorRepositoryGitCommitChangeParserWorker');
      $task->setData(
        array(
          'commitID' => $commit->getID(),
        ));
      $task->save();
    }
  }

  protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    list($stdout) = $repository->execxLocalCommand(
      'log -n 1 --format=%s %s --',
      '%T',
      $commit->getCommitIdentifier());

    $commit_hash = $commit->getCommitIdentifier();
    $tree_hash = trim($stdout);

    return array(
      array(ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT,
            $commit_hash),
      array(ArcanistDifferentialRevisionHash::HASH_GIT_TREE,
            $tree_hash),
    );
  }

}
