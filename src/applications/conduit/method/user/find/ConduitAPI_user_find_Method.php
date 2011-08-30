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
 * @group conduit
 */
final class ConduitAPI_user_find_Method
  extends ConduitAPI_user_Method {

  public function getMethodDescription() {
    return "Find user PHIDs which correspond to provided user aliases. ".
           "Returns NULL for aliases which do have any corresponding PHIDs.";
  }

  public function defineParamTypes() {
    return array(
      'aliases' => 'required nonempty list<string>'
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, phid>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $users = id(new PhabricatorUser())->loadAllWhere(
      'username in (%Ls)',
      $request->getValue('aliases'));

    return mpull($users, 'getPHID', 'getUsername');
  }

}
