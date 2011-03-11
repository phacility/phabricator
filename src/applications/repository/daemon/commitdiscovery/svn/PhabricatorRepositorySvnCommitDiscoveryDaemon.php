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

class PhabricatorRepositorySvnCommitDiscoveryDaemon
  extends PhabricatorRepositoryCommitDiscoveryDaemon {

  protected function discoverCommits() {
    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_SVN) {
      throw new Exception("Repository is not a svn repository.");
    }

    $repository_phid = $repository->getPHID();

    $uri = $repository->getDetail('remote-uri');
    list($xml) = execx(
      'svn log --xml --non-interactive --quiet --limit 1 %s@HEAD',
      $uri);

    // TODO: We need to slam the XML output into valid UTF-8.

    $log = new SimpleXMLElement($xml);
    $entry = $log->logentry[0];
    $commit = (int)$entry['revision'];

    if ($this->isKnownCommit($commit)) {
      return false;
    }

    $this->discoverCommit($commit);

    return true;
  }

  private function discoverCommit($commit) {
    $discover = array();
    $largest_known = $commit - 1;
    while ($largest_known > 0 && !$this->isKnownCommit($largest_known)) {
      $largest_known--;
    }

    $repository = $this->getRepository();
    $uri = $repository->getDetail('remote-uri');

    for ($ii = $largest_known + 1; $ii <= $commit; $ii++) {
      list($xml) = execx(
        'svn log --xml --non-interactive --quiet --limit 1 %s@%d',
        $uri,
        $ii);
      $log = new SimpleXMLElement($xml);
      $entry = $log->logentry[0];

      $identifier = (int)$entry['revision'];
      $epoch = (int)strtotime((string)$entry->date[0]);

      $this->recordCommit($identifier, $epoch);
    }
  }

}
