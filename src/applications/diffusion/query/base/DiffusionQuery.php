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

abstract class DiffusionQuery {

  private $request;

  final protected function __construct() {
    // <protected>
  }

  protected static function newQueryObject(
    $base_class,
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    $map = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT        => 'Git',
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL  => 'Mercurial',
      PhabricatorRepositoryType::REPOSITORY_TYPE_SVN        => 'SVN',
    );

    $name = idx($map, $repository->getVersionControlSystem());
    if (!$name) {
      throw new Exception("Unsupported VCS!");
    }

    $class = str_replace('Diffusion', 'Diffusion'.$name, $base_class);
    $obj = new $class();
    $obj->request = $request;

    return $obj;
  }

  final protected function getRequest() {
    return $this->request;
  }

  abstract protected function executeQuery();
}
