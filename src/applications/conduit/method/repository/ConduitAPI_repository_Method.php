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
 * @group conduit
 */
abstract class ConduitAPI_repository_Method extends ConduitAPIMethod {

  protected function buildDictForRepository(PhabricatorRepository $repository) {
    return array(
      'name'        => $repository->getName(),
      'phid'        => $repository->getPHID(),
      'callsign'    => $repository->getCallsign(),
      'vcs'         => $repository->getVersionControlSystem(),
      'uri'         => PhabricatorEnv::getProductionURI($repository->getURI()),
      'remoteURI'   => (string)$repository->getPublicRemoteURI(),
      'tracking'    => $repository->getDetail('tracking-enabled'),
      'description' => $repository->getDetail('description'),
    );
  }

}
