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
final class ConduitAPI_differential_createrawdiff_Method
  extends ConduitAPI_differential_Method {

  public function getMethodDescription() {
    return "Create a new Differential diff from a raw diff source.";
  }

  public function defineParamTypes() {
    return array(
      'diff' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $raw_diff = $request->getValue('diff');

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($raw_diff);
    $diff = DifferentialDiff::newFromRawChanges($changes);

    $diff->setLintStatus(DifferentialLintStatus::LINT_SKIP);
    $diff->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP);

    $diff->setAuthorPHID($request->getUser()->getPHID());
    $diff->setCreationMethod('web');
    $diff->save();

    return $this->buildDiffInfoDictionary($diff);
  }

}
