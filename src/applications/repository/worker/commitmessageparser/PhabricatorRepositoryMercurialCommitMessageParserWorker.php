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

final class PhabricatorRepositoryMercurialCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    list($stdout) = $repository->execxLocalCommand(
      'log --template %s --rev %s',
      '{author}\\n{desc}',
      $commit->getCommitIdentifier());

    list($author, $message) = explode("\n", $stdout, 2);

    $author = phutil_utf8ize($author);
    $message = phutil_utf8ize($message);
    $message = trim($message);

    $this->updateCommitData($author, $message);

    if ($this->shouldQueueFollowupTasks()) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorRepositoryMercurialCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

  protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $commit_hash = $commit->getCommitIdentifier();

    return array(
      array(ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT,
            $commit_hash),
    );
  }

}
