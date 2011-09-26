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

final class DiffusionMercurialFileContentQuery
  extends DiffusionFileContentQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    list($corpus) = $repository->execxLocalCommand(
      'cat --rev %s -- %s',
      $commit,
      $path);

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }


  protected function tokenizeLine($line) {
    // TODO: Support blame.
    throw new Exception(
      "Diffusion does not currently support blame for Mercurial.");
  }

}
