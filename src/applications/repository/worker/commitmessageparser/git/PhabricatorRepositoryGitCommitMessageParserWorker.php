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

class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $local_path = $repository->getDetail('local-path');

    list($info) = execx(
      '(cd %s && git log -n 1 --pretty=format:%%an%%x00%%B %s)',
      $local_path,
      $commit->getCommitIdentifier());


    list($author, $message) = explode("\0", $info);

    // Make sure these are valid UTF-8.
    $author = phutil_utf8ize($author);
    $message = phutil_utf8ize($message);

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

}
