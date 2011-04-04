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

class DiffusionSvnRequest extends DiffusionRequest {

  private $loadedCommit;

  protected function initializeFromAphrontRequestDictionary(array $data) {
    parent::initializeFromAphrontRequestDictionary($data);
    if (!strncmp($this->path, ':', 1)) {
      $this->path = substr($this->path, 1);
      $this->path = ltrim($this->path, '/');
    }
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }

    if (!$this->loadedCommit) {
      $this->loadedCommit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
        'repositoryID = %d ORDER BY epoch DESC LIMIT 1',
        $this->getRepository()->getID())->getCommitIdentifier();
    }

    return $this->loadedCommit;
  }

}
