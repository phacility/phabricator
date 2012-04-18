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
final class ConduitAPI_phid_info_Method
  extends ConduitAPI_phid_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'phid.query'.";
  }

  public function getMethodDescription() {
    return "Retrieve information about an arbitrary PHID.";
  }

  public function defineParamTypes() {
    return array(
      'phid' => 'required phid',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-PHID' => 'No such object exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $phid = $request->getValue('phid');

    $handles = id(new PhabricatorObjectHandleData(array($phid)))
      ->loadHandles();

    $handle = $handles[$phid];
    if (!$handle->isComplete()) {
      throw new ConduitException('ERR-BAD-PHID');
    }

    return $this->buildHandleInformationDictionary($handle);
  }

}
