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

final class DiffusionMercurialFileContentQuery
  extends DiffusionFileContentQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    if ($this->getNeedsBlame()) {
      // NOTE: We're using "--number" instead of "--changeset" because there is
      // no way to get "--changeset" to show us the full commit hashes.
      list($corpus) = $repository->execxLocalCommand(
        'annotate --user --number --rev %s -- %s',
        $commit,
        $path);
    } else {
      list($corpus) = $repository->execxLocalCommand(
        'cat --rev %s -- %s',
        $commit,
        $path);
    }

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }

  protected function tokenizeLine($line) {
    $matches = null;

    preg_match(
      '/^(.*?)\s+([0-9]+): (.*)$/',
      $line,
      $matches);

    return array($matches[2], $matches[1], $matches[3]);
  }

  /**
   * Convert local revision IDs into full commit identifier hashes.
   */
  protected function processRevList(array $rev_list) {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $revs = array_unique($rev_list);
    foreach ($revs as $key => $rev) {
      $revs[$key] = '--rev '.(int)$rev;
    }

    list($stdout) = $repository->execxLocalCommand(
      'log --template=%s %C',
      '{rev} {node}\\n',
      implode(' ', $revs));

    $map = array();
    foreach (explode("\n", trim($stdout)) as $line) {
      list($rev, $node) = explode(' ', $line);
      $map[$rev] = $node;
    }

    foreach ($rev_list as $k => $rev) {
      $rev_list[$k] = $map[$rev];
    }

    return $rev_list;
  }

}
