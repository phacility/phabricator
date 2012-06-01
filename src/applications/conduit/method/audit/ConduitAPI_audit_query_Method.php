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
final class ConduitAPI_audit_query_Method extends ConduitAPI_audit_Method {

  public function getMethodDescription() {
    return "Query audit requests.";
  }

  public function defineParamTypes() {
    return array(
      'auditorPHIDs'  => 'optional list<phid>',
      'commitPHIDs'   => 'optional list<phid>',
      'status'        => 'optional enum<"status-any", "status-open"> '.
                         '(default = "status-any")',
      'offset'        => 'optional int',
      'limit'         => 'optional int (default = 100)',
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

    $query = new PhabricatorAuditQuery();

    $auditor_phids = $request->getValue('auditorPHIDs', array());
    if ($auditor_phids) {
      $query->withAuditorPHIDs($auditor_phids);
    }

    $commit_phids = $request->getValue('commitPHIDs', array());
    if ($commit_phids) {
      $query->withCommitPHIDs($commit_phids);
    }

    $status = $request->getValue('status', PhabricatorAuditQuery::STATUS_ANY);
    $query->withStatus($status);

    $query->setOffset($request->getValue('offset', 0));
    $query->setLimit($request->getValue('limit', 100));

    $requests = $query->execute();

    $results = array();
    foreach ($requests as $request) {
      $results[] = array(
        'id'              => $request->getID(),
        'commitPHID'      => $request->getCommitPHID(),
        'auditorPHID'     => $request->getAuditorPHID(),
        'reasons'         => $request->getAuditReasons(),
        'status'          => $request->getAuditStatus(),
      );
    }

    return $results;
  }


}
