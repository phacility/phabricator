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

/**
 * @group diffusion
 */
final class DiffusionGitRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return true;
  }

  protected function didInitialize() {
    $repository = $this->getRepository();

    if (!Filesystem::pathExists($repository->getLocalPath())) {
      $this->raiseCloneException();
    }

    if (!$this->commit) {
      return;
    }

    // Expand short commit names and verify

    $future = $repository->getLocalCommandFuture(
      'cat-file --batch');
    $future->write($this->commit);
    list($stdout) = $future->resolvex();

    list($hash, $type) = explode(' ', $stdout);
    if ($type == 'missing') {
      throw new Exception("Bad commit '{$this->commit}'.");
    }

    switch ($type) {
      case 'tag':
        $this->commitType = 'tag';

        $matches = null;
        $ok = preg_match(
          '/^object ([a-f0-9]+)$.*?\n\n(.*)$/sm',
          $stdout,
          $matches);
        if (!$ok) {
          throw new Exception(
            "Unparseable output from cat-file: {$stdout}");
        }

        $hash = $matches[1];
        $this->tagContent = trim($matches[2]);
        break;
      case 'commit':
        break;
      default:
        throw new AphrontUsageException(
          "Invalid Object Name",
          "The reference '{$this->commit}' does not name a valid ".
          "commit or a tag in this repository.");
        break;
    }

    $this->commit = $hash;
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }
    if ($this->repository) {
      return $this->repository->getDetail('default-branch', 'master');
    }
    throw new Exception("Unable to determine branch!");
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    $remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE;
    return $remote.'/'.$this->getBranch();
  }

  public function getStableCommitName() {
    if (!$this->stableCommitName) {
      if ($this->commit) {
        $this->stableCommitName = $this->commit;
      } else {
        $branch = $this->getBranch();
        list($stdout) = $this->getRepository()->execxLocalCommand(
          'rev-parse --verify %s/%s',
          DiffusionBranchInformation::DEFAULT_GIT_REMOTE,
          $branch);
        $this->stableCommitName = trim($stdout);
      }
    }
    return substr($this->stableCommitName, 0, 16);
  }

}
