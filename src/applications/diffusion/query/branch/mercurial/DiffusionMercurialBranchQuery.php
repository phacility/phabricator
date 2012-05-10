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

final class DiffusionMercurialBranchQuery extends DiffusionBranchQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      '--debug branches');
    $branch_info = ArcanistMercurialParser::parseMercurialBranches($stdout);

    $branches = array();
    foreach ($branch_info as $name => $info) {
      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($info['rev']);
      $branches[] = $branch;
    }

    if ($this->getOffset()) {
      $branches = array_slice($branches, $this->getOffset());
    }

    if ($this->getLimit()) {
      $branches = array_slice($branches, 0, $this->getLimit());
    }

    return $branches;
  }

}
