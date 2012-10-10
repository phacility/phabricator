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
final class ConduitAPI_differential_createrevision_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Create a new Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'diffid' => 'required diffid',
      'fields' => 'required dict',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF' => 'Bad diff ID.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $fields = $request->getValue('fields');

    $diff = id(new DifferentialDiff())->load($request->getValue('diffid'));
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $revision = DifferentialRevisionEditor::newRevisionFromConduitWithDiff(
      $fields,
      $diff,
      $request->getUser());

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
