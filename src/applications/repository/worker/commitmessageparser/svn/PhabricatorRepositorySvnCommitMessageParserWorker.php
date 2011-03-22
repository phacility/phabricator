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

class PhabricatorRepositorySvnCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $uri = $repository->getDetail('remote-uri');

    list($xml) = execx(
      'svn log --xml --limit 1 --non-interactive %s@%d',
      $uri,
      $commit->getCommitIdentifier());

    // Subversion may send us back commit messages which won't parse because
    // they have non UTF-8 garbage in them. Slam them into valid UTF-8.
    $xml = phutil_utf8ize($xml);

    $log = new SimpleXMLElement($xml);
    $entry = $log->logentry[0];

    $author = (string)$entry->author;
    $message = (string)$entry->msg;

    $this->updateCommitData($author, $message);

    $task = new PhabricatorWorkerTask();
    $task->setTaskClass('PhabricatorRepositorySvnCommitChangeParserWorker');
    $task->setData($commit->getID());
    $task->save();
  }

}
