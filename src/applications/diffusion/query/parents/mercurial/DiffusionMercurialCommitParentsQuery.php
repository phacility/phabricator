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

final class DiffusionMercurialCommitParentsQuery
  extends DiffusionCommitParentsQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      'log --debug --limit 1 --template={parents} --rev %s',
      $drequest->getStableCommitName());

    $hashes = preg_split('/\s+/', trim($stdout));
    foreach ($hashes as $key => $value) {
      // Mercurial parents look like "23:ad9f769d6f786fad9f76d9a" -- we want
      // to strip out the local rev part.
      list($local, $global) = explode(':', $value);
      $hashes[$key] = $global;

      // With --debug we get 40-character hashes but also get the "000000..."
      // hash for missing parents; ignore it.
      if (preg_match('/^0+$/', $global)) {
        unset($hashes[$key]);
      }
    }

    return self::loadCommitsByIdentifiers($hashes);
  }
}
