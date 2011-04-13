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

class ConduitAPI_diffusion_getcommits_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Diffusion commit information.";
  }

  public function defineParamTypes() {
    return array(
      'commits' => 'required list<string>',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $results = array();

    $commits = $request->getValue('commits');
    $commits = array_fill_keys($commits, array());
    foreach ($commits as $name => $info) {
      $matches = null;
      if (!preg_match('/^r([A-Z]+)([0-9a-f]+)$/', $name, $matches)) {
        $results[$name] = array(
          'error' => 'ERR-UNPARSEABLE',
        );
        unset($commits[$name]);
        continue;
      }
      $commits[$name] = array(
        'callsign'          => $matches[1],
        'commitIdentifier'  => $matches[2],
      );
    }

    if (!$commits) {
      return $results;
    }

    $callsigns = ipull($commits, 'callsign');
    $callsigns = array_unique($callsigns);
    $repos = id(new PhabricatorRepository())->loadAllWhere(
      'callsign IN (%Ls)',
      $callsigns);
    $repos = mpull($repos, null, 'getCallsign');

    foreach ($commits as $name => $info) {
      $repo = idx($repos, $info['callsign']);
      if (!$repo) {
        $results[$name] = $info + array(
          'error' => 'ERR-UNKNOWN-REPOSITORY',
        );
        unset($commits[$name]);
        continue;
      }
      $commits[$name] += array(
        'repositoryPHID' => $repo->getPHID(),
        'repositoryID' => $repo->getID(),
      );
    }

    if (!$commits) {
      return $results;
    }

    $conn_r = id(new PhabricatorRepositoryCommit())->establishConnection('r');

    $groups = array();
    foreach ($commits as $name => $commit) {
      $groups[$commit['repositoryID']][] = $commit['commitIdentifier'];
    }

    // NOTE: MySQL goes crazy and does a massive table scan if we build a more
    // sensible version of this query. Make sure the query play is OK if you
    // attempt to reduce the craziness here.
    $query = array();
    foreach ($groups as $repository_id => $identifiers) {
      $query[] = qsprintf(
        $conn_r,
        'SELECT * FROM %T WHERE repositoryID = %d
          AND commitIdentifier IN (%Ls)',
        id(new PhabricatorRepositoryCommit())->getTableName(),
        $repository_id,
        $identifiers);
    }
    $cdata = queryfx_all(
      $conn_r,
      '%Q',
      implode(' UNION ALL ', $query));

    $cobjs = id(new PhabricatorRepositoryCommit())->loadAllFromArray($cdata);
    $cobjs = mgroup($cobjs, 'getRepositoryID', 'getCommitIdentifier');
    foreach ($commits as $name => $commit) {
      $repo_id = $commit['repositoryID'];
      unset($commits[$name]['repositoryID']);

      if (empty($cobjs[$commit['repositoryID']][$commit['commitIdentifier']])) {
        $results[$name] = $commit + array(
          'error' => 'ERR-UNKNOWN-COMMIT',
        );
        unset($commits[$name]);
        continue;
      }

      $cobj_arr = $cobjs[$commit['repositoryID']][$commit['commitIdentifier']];
      $cobj = head($cobj_arr);

      $commits[$name] += array(
        'epoch'      => $cobj->getEpoch(),
        'commitPHID' => $cobj->getPHID(),
        'commitID'   => $cobj->getID(),
      );
    }

    if (!$commits) {
      return $results;
    }

    $commit_ids = ipull($commits, 'commitID');
    $data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
      'commitID in (%Ld)',
      $commit_ids);
    $data = mpull($data, null, 'getCommitID');

    foreach ($commits as $name => $commit) {
      if (isset($data[$commit['commitID']])) {
        $dobj = $data[$commit['commitID']];
        $commits[$name] += array(
          'commitMessage' => $dobj->getCommitMessage(),
          'commitDetails' => $dobj->getCommitDetails(),
        );
      }
      unset($commits[$name]['commitID']);
    }

    $commit_phids = ipull($commits, 'commitPHID');
    $rev_conn_r = id(new DifferentialRevision())->establishConnection('r');
    $revs = queryfx_all(
      $rev_conn_r,
      'SELECT r.id id, r.phid phid, c.commitPHID commitPHID FROM %T r JOIN %T c
        ON r.id = c.revisionID
        WHERE c.commitPHID in (%Ls)',
      id(new DifferentialRevision())->getTableName(),
      DifferentialRevision::TABLE_COMMIT,
      $commit_phids);

    $revs = ipull($revs, null, 'commitPHID');
    foreach ($commits as $name => $commit) {
      if (isset($revs[$commit['commitPHID']])) {
        $rev = $revs[$commit['commitPHID']];
        $commits[$name] += array(
          'differentialRevisionID'    => 'D'.$rev['id'],
          'differentialRevisionPHID'  => $rev['phid'],
        );
      }
    }

    foreach ($commits as $name => $commit) {
      $results[$name] = $commit;
    }

    return $results;
  }

}
