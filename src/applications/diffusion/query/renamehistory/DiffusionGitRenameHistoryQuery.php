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

final class DiffusionGitRenameHistoryQuery
  extends DiffusionRenameHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    list($err, $stdout) = $repository->execLocalCommand(
      'log --format=%s --follow --find-copies-harder -M -C --summary '.
        '%s..%s -- %s',
      '%x20',
      $this->getOldCommit(),
      $drequest->getCommit(),
      $drequest->getPath());

    if ($err) {
      return null;
    }

    $lines = explode("\n", $stdout);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines);

    $name = null;
    foreach ($lines as $line) {
      list($action, $info) = explode(' ', $line, 2);
      switch ($action) {
        case 'rename':
          // We support these cases:
          // rename README => README.txt (100%)
          // rename src/README => README (100%)
          // rename src/{README => README.txt} (100%)
          // rename {resources => rsrc}/README (100%)
          // rename src/{aphront => }/README (100%)
          // rename src/{ => aphront}/README (100%)
          // rename src/{docs => ducks}/README (100%)
          $matches = null;
          $ok = preg_match(
            '/^(.*){(.*) => (.*)}(.*) \([0-9%]+\)$/',
            $info,
            $matches);
          if ($ok) {
            $name = $matches[1].ltrim($matches[2].$matches[4], '/');
          } else {
            $ok = preg_match(
              '/^(.*) => (.*) \([0-9%]+\)$/',
              $info,
              $matches);
            if (!$ok) {
              throw new Exception(
                "Unparseable git log --summary line: {$line}.");
            }
            $name = $matches[1];
          }

          break;
        case 'create':
          // create mode 100644 <filename>
          $this->setWasCreated(true);
          break;
        default:
          // Anything else we care about?
          break;
      }
    }

    return $name;
  }

}
