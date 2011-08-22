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

class PhabricatorRepositoryCommitTaskDaemon
  extends PhabricatorRepositoryDaemon {

  final public function run() {
    do {
      $iterator = new PhabricatorTimelineIterator('cmittask', array('cmit'));
      foreach ($iterator as $event) {
        $data = $event->getData();

        if (!$data) {
          // TODO: This event can't be processed, provide some way to
          // communicate that?
          continue;
        }

        $commit = id(new PhabricatorRepositoryCommit())->load($data['id']);
        if (!$commit) {
          // TODO: Same as above.
          continue;
        }

        // TODO: Cache these.
        $repository = id(new PhabricatorRepository())->load(
          $commit->getRepositoryID());
        if (!$repository) {
          // TODO: As above, although this almost certainly means the user just
          // deleted the repository and we're correct to ignore the event in
          // the timeline.
          continue;
        }

        $vcs = $repository->getVersionControlSystem();
        switch ($vcs) {
          case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
            $class = 'PhabricatorRepositoryGitCommitMessageParserWorker';
            $task = new PhabricatorWorkerTask();
            $task->setTaskClass($class);
            $task->setData(
              array(
                'commitID' => $commit->getID(),
              ));
            $task->save();
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
            $class = 'PhabricatorRepositorySvnCommitMessageParserWorker';
            $task = new PhabricatorWorkerTask();
            $task->setTaskClass($class);
            $task->setData(
              array(
                'commitID' => $commit->getID(),
              ));
            $task->save();
            break;
          default:
            throw new Exception("Unknown repository type.");
        }

        $this->stillWorking();
      }
      sleep(1);
      $this->stillWorking();
    } while (true);
  }

}
