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

final class DiffusionDiffController extends DiffusionController {

  public function willProcessRequest(array $data) {
    $request = $this->getRequest();
    if ($request->getStr('ref')) {
      $parts = explode(';', $request->getStr('ref'));
      $data['path'] = idx($parts, 0);
      $data['commit'] = idx($parts, 1);
    }

    $this->diffusionRequest = DiffusionRequest::newFromAphrontRequestDictionary(
      $data);
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();

    $diff_query = DiffusionDiffQuery::newFromDiffusionRequest($drequest);
    $changeset = $diff_query->loadChangeset();

    if (!$changeset) {
      return new Aphront404Response();
    }

    $parser = new DifferentialChangesetParser();
    $parser->setChangeset($changeset);
    $parser->setRenderingReference($diff_query->getRenderingReference());
    $parser->setWhitespaceMode(
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL);

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);
    $output = $parser->render($range_s, $range_e, $mask);

    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'changeset' => $output,
        ));
  }
}
