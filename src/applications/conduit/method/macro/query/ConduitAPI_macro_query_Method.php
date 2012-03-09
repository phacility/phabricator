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
final class ConduitAPI_macro_query_Method extends ConduitAPI_macro_Method {

  public function getMethodDescription() {
    return "Retrieve image macro information.";
  }

  public function defineParamTypes() {
    return array(
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $macros = id(new PhabricatorFileImageMacro())->loadAll();

    $files = array();
    if ($macros) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        mpull($macros, 'getFilePHID'));
      $files = mpull($files, null, 'getPHID');
    }

    $results = array();
    foreach ($macros as $macro) {
      if (empty($files[$macro->getFilePHID()])) {
        continue;
      }
      $results[$macro->getName()] = array(
        'uri' => $files[$macro->getFilePHID()]->getBestURI(),
      );
    }

    return $results;
  }

}
