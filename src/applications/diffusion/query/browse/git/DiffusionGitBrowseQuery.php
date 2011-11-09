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

final class DiffusionGitBrowseQuery extends DiffusionBrowseQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    if ($path == '') {
      // Fast path to improve the performance of the repository view; we know
      // the root is always a tree at any commit and always exists.
      $stdout = 'tree';
    } else {
      try {
        list($stdout) = $repository->execxLocalCommand(
          'cat-file -t %s:%s',
          $commit,
          $path);
      } catch (CommandException $e) {
        $stderr = $e->getStdErr();
        if (preg_match('/^fatal: Not a valid object name/', $stderr)) {
          // Grab two logs, since the first one is when the object was deleted.
          list($stdout) = $repository->execxLocalCommand(
            'log -n2 --format="%%H" %s -- %s',
            $commit,
            $path);
          $stdout = trim($stdout);
          if ($stdout) {
            $commits = explode("\n", $stdout);
            $this->reason = self::REASON_IS_DELETED;
            $this->deletedAtCommit = idx($commits, 0);
            $this->existedAtCommit = idx($commits, 1);
            return array();
          }

          $this->reason = self::REASON_IS_NONEXISTENT;
          return array();
        } else {
          throw $e;
        }
      }
    }

    if (trim($stdout) == 'blob') {
      $this->reason = self::REASON_IS_FILE;
      return array();
    }

    if ($this->shouldOnlyTestValidity()) {
      return true;
    }

    list($stdout) = $repository->execxLocalCommand(
      'ls-tree -l %s:%s',
      $commit,
      $path);

    $results = array();
    foreach (explode("\n", rtrim($stdout)) as $line) {
      list($mode, $type, $hash, $size, $name) = preg_split('/\s+/', $line);
      if ($type == 'tree') {
        $file_type = DifferentialChangeType::FILE_DIRECTORY;
      } else {
        $file_type = DifferentialChangeType::FILE_NORMAL;
      }

      $result = new DiffusionRepositoryPath();
      $result->setPath($name);
      $result->setHash($hash);
      $result->setFileType($file_type);
      $result->setFileSize($size);

      $results[] = $result;
    }

    return $results;
  }

}
