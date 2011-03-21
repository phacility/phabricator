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

class PhabricatorRepositoryGitFetchDaemon
  extends PhabricatorRepositoryDaemon {

  public function run() {
    $repository = $this->loadRepository();

    if ($repository->getVersionControlSystem() != 'git') {
      throw new Exception("Not a git repository!");
    }

    $tracked = $repository->getDetail('tracking-enabled');
    if (!$tracked) {
      throw new Exception("Tracking is not enabled for this repository.");
    }

    $local_path = $repository->getDetail('local-path');
    $remote_uri = $repository->getDetail('remote-uri');

    if (!$local_path) {
      throw new Exception("No local path is available for this repository.");
    }

    while (true) {
      if (!Filesystem::pathExists($local_path)) {
        if (!$remote_uri) {
          throw new Exception("No remote URI is available.");
        }
        execx('mkdir -p %s', dirname($local_path));
        execx('git clone %s %s', $remote_uri, rtrim($local_path, '/'));
      } else {
        execx('(cd %s && git fetch --all)', $local_path);
      }
      $this->sleep($repository->getDetail('pull-frequency', 15));
    }
  }

}
