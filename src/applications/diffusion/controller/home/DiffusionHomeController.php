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

class DiffusionHomeController extends DiffusionController {

  public function processRequest() {

    // TODO: Restore "shortcuts" feature.

    $repositories = id(new PhabricatorRepository())->loadAll();

    $commit = new PhabricatorRepositoryCommit();
    $conn_r = $commit->establishConnection('r');

    // TODO: Both these queries are basically bogus and have total trash for
    // query plans, and don't return the right results. Build a cache instead.
    // These are just pulling data with approximately the right look to it.
    $commits = $commit->loadAllWhere(
      '1 = 1 GROUP BY repositoryPHID');
    $commits = mpull($commits, null, 'getRepositoryPHID');

    $commit_counts = queryfx_all(
      $conn_r,
      'SELECT repositoryPHID, count(*) N FROM %T
        GROUP BY repositoryPHID',
      $commit->getTableName());
    $commit_counts = ipull($commit_counts, 'N', 'repositoryPHID');

    $rows = array();
    foreach ($repositories as $repository) {
      $phid = $repository->getPHID();
      $commit = idx($commits, $phid);
      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repository->getCallsign().'/',
          ),
          phutil_escape_html($repository->getName())),
        $repository->getVersionControlSystem(),
        idx($commit_counts, $phid, 0),
        $commit
          ? $commit->getCommitIdentifier()
          : null, // TODO: Link/format
        $commit
          ? phabricator_format_timestamp($commit->getEpoch())
          : null,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Repository',
        'VCS',
        'Size',
        'Last',
        'Committed',
      ));
    $table->setColumnClasses(
      array(
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Browse Repositories');
    $panel->appendChild($table);

    $crumbs = $this->buildCrumbs();

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $panel,
      ),
      array(
        'title' => 'Diffusion',
      ));
  }

}
