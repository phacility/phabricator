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
final class ConduitAPI_user_getcurrentstatus_Method
  extends ConduitAPI_user_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Get current status (away or sporadic) of specified users.";
  }

  public function defineParamTypes() {
    return array(
      'userPHIDs' => 'required list',
    );
  }

  public function defineReturnType() {
    return 'dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $statuses = id(new PhabricatorUserStatus())->loadAllWhere(
      'userPHID IN (%Ls) AND UNIX_TIMESTAMP() BETWEEN dateFrom AND dateTo',
      $request->getValue('userPHIDs'));

    $return = array();
    foreach ($statuses as $status) {
      $return[$status->getUserPHID()] = array(
        'fromEpoch' => $status->getDateFrom(),
        'toEpoch' => $status->getDateTo(),
        'status' => $status->getTextStatus(),
      );
    }
    return $return;
  }

}
