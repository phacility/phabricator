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
final class ConduitAPI_phid_lookup_Method
  extends ConduitAPI_phid_Method {

  public function getMethodDescription() {
    return "Look up objects by name.";
  }

  public function defineParamTypes() {
    return array(
      'names' => 'required list<string>',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $names = $request->getValue('names');
    $phids = array();
    foreach ($names as $name) {
      $phid = PhabricatorPHID::fromObjectName($name);
      if ($phid) {
        $phids[$name] = $phid;
      }
    }
    
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $result = array();
    foreach ($phids as $name => $phid) {
      if (isset($handles[$phid]) && $handles[$phid]->isComplete()) {
        $result[$name] = $this->buildHandleInformationDictionary(
          $handles[$phid]);
      }
    }

    return $result;
  }

}
