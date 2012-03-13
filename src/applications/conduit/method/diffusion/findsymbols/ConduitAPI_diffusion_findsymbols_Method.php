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
final class ConduitAPI_diffusion_findsymbols_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Diffusion symbol information.";
  }

  public function defineParamTypes() {
    return array(
      'name'        => 'optional string',
      'namePrefix'  => 'optional string',
      'language'    => 'optional string',
      'type'        => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $name = $request->getValue('name');
    $name_prefix = $request->getValue('namePrefix');
    $language = $request->getValue('language');
    $type = $request->getValue('type');

    $query = new DiffusionSymbolQuery();
    if ($name !== null) {
      $query->setName($name);
    }
    if ($name_prefix !== null) {
      $query->setNamePrefix($name_prefix);
    }
    if ($language !== null) {
      $query->setLanguage($language);
    }
    if ($type !== null) {
      $query->setType($type);
    }

    $query->needPaths(true);
    $query->needArcanistProjects(true);
    $query->needRepositories(true);

    $results = $query->execute();

    $response = array();
    foreach ($results as $result) {
      $response[] = array(
        'name'        => $result->getSymbolName(),
        'type'        => $result->getSymbolType(),
        'language'    => $result->getSymbolLanguage(),
        'path'        => $result->getPath(),
        'line'        => $result->getLineNumber(),
        'uri'         => PhabricatorEnv::getProductionURI($result->getURI()),
      );
    }

    return $response;
  }

}
