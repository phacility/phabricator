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

final class PhabricatorRepositorySvnCommitDiscoveryDaemon
  extends PhabricatorRepositoryCommitDiscoveryDaemon {

  protected function discoverCommits() {
    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_SVN) {
      throw new Exception("Repository is not a svn repository.");
    }

    $uri = $this->getBaseSVNLogURI();
    list($xml) = $repository->execxRemoteCommand(
      'log --xml --quiet --limit 1 %s@HEAD',
      $uri);

    $results = $this->parseSVNLogXML($xml);
    $commit = key($results);
    $epoch  = reset($results);

    if ($this->isKnownCommit($commit)) {
      return false;
    }

    $this->discoverCommit($commit, $epoch);

    return true;
  }

  private function discoverCommit($commit, $epoch) {
    $uri = $this->getBaseSVNLogURI();
    $repository = $this->getRepository();

    $discover = array(
      $commit => $epoch,
    );
    $upper_bound = $commit;

    $limit = 1;
    while ($upper_bound > 1 && !$this->isKnownCommit($upper_bound)) {
      // Find all the unknown commits on this path. Note that we permit
      // importing an SVN subdirectory rather than the entire repository, so
      // commits may be nonsequential.
      list($err, $xml, $stderr) = $repository->execRemoteCommand(
        ' log --xml --quiet --limit %d %s@%d',
        $limit,
        $uri,
        $upper_bound - 1);
      if ($err) {
        if (preg_match('/(path|File) not found/', $stderr)) {
          // We've gone all the way back through history and this path was not
          // affected by earlier commits.
          break;
        } else {
          throw new Exception("svn log error #{$err}: {$stderr}");
        }
      }
      $discover += $this->parseSVNLogXML($xml);

      $upper_bound = min(array_keys($discover));

      // Discover 2, 4, 8, ... 256 logs at a time. This allows us to initially
      // import large repositories fairly quickly, while pulling only as much
      // data as we need in the common case (when we've already imported the
      // repository and are just grabbing one commit at a time).
      $limit = min($limit * 2, 256);
    }

    // NOTE: We do writes only after discovering all the commits so that we're
    // never left in a state where we've missed commits -- if the discovery
    // script terminates it can always resume and restore the import to a good
    // state. This is also why we sort the discovered commits so we can do
    // writes forward from the smallest one.

    ksort($discover);
    foreach ($discover as $commit => $epoch) {
      $this->recordCommit($commit, $epoch);
    }
  }

  private function parseSVNLogXML($xml) {
    $xml = phutil_utf8ize($xml);

    $result = array();

    $log = new SimpleXMLElement($xml);
    foreach ($log->logentry as $entry) {
      $commit = (int)$entry['revision'];
      $epoch  = (int)strtotime((string)$entry->date[0]);
      $result[$commit] = $epoch;
    }

    return $result;
  }


  private function getBaseSVNLogURI() {
    $repository = $this->getRepository();

    $uri = $repository->getDetail('remote-uri');
    $subpath = $repository->getDetail('svn-subpath');

    return $uri.$subpath;
  }
}
