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


/**
 * Records information about symbol locations in a codebase, like where classes
 * and functions are defined.
 *
 * Query symbols with @{class:DiffusionSymbolQuery}.
 *
 * @group diffusion
 */
class PhabricatorRepositorySymbol extends PhabricatorRepositoryDAO {

  protected $arcanistProjectID;
  protected $symbolName;
  protected $symbolType;
  protected $symbolLanguage;
  protected $pathID;
  protected $lineNumber;

  private $path = false;
  private $arcanistProject = false;
  private $repository = false;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function getURI() {
    $repo = $this->getRepository();
    $file = $this->getPath();
    $line = $this->getLineNumber();

    $drequest = DiffusionRequest::newFromAphrontRequestDictionary(
      array(
        'callsign' => $repo->getCallsign(),
      ));
    $branch = $drequest->getBranchURIComponent($drequest->getBranch());
    $file = $branch.ltrim($file, '/');

    return '/diffusion/'.$repo->getCallsign().'/browse/'.$file.'$'.$line;
  }

  public function getPath() {
    if ($this->path === false) {
      throw new Exception('Call attachPath() before getPath()!');
    }
    return $this->path;
  }

  public function attachPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getRepository() {
    if ($this->repository === false) {
      throw new Exception('Call attachRepository() before getRepository()!');
    }
    return $this->repository;
  }

  public function attachRepository($repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getArcanistProject() {
    if ($this->arcanistProject === false) {
      throw new Exception(
        'Call attachArcanistProject() before getArcanistProject()!');
    }
    return $this->arcanistProject;
  }

  public function attachArcanistProject($project) {
    $this->arcanistProject = $project;
    return $this;
  }

}
