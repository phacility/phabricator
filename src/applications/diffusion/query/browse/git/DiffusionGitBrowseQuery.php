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
    $repository = $this->getRepository();
    $path = $this->getPath();
    $commit = nonempty($this->getCommit(), 'HEAD');

    $local_path = $repository->getDetail('local-path');

    $git = PhabricatorEnv::getEnvConfig('git.path');

    try {
      list($stdout) = execx(
        "(cd %s && %s cat-file -t %s:%s)",
        $local_path,
        $git,
        $commit,
        $path);
    } catch (CommandException $e) {
      if (preg_match('/^fatal: Not a valid object name/', $e->getStderr())) {
        $this->reason = self::REASON_IS_NONEXISTENT;
        return array();
      } else {
        throw $e;
      }
    }

    if (trim($stdout) == 'blob') {
      $this->reason = self::REASON_IS_FILE;
      return array();
    }

    list($stdout) = execx(
      "(cd %s && %s ls-tree -l %s:%s)",
      $local_path,
      $git,
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
