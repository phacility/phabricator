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
    foreach ($repositories as $key => $repository) {
      if (!$repository->getDetail('tracking-enabled')) {
        unset($repositories[$key]);
      }
    }

    $commit = new PhabricatorRepositoryCommit();
    $conn_r = $commit->establishConnection('r');

    // TODO: These queries are pretty bogus.

    $commits = array();
    $commit_counts = array();

    $max_epoch = queryfx_all(
      $commit->establishConnection('r'),
      'SELECT repositoryID, MAX(epoch) maxEpoch FROM %T GROUP BY repositoryID',
      $commit->getTableName());

    if ($max_epoch) {
      $sql = array();
      foreach ($max_epoch as $head) {
        $sql[] = '('.(int)$head['repositoryID'].', '.(int)$head['maxEpoch'].')';
      }

      // NOTE: It's possible we'll pull multiple commits for some repository
      // here but it reduces query cost around 3x to unique them in PHP rather
      // than apply GROUP BY in MySQL.
      $commits = $commit->loadAllWhere(
        '(repositoryID, epoch) IN (%Q)',
        implode(', ', $sql));
      $commits = mpull($commits, null, 'getRepositoryID');

      $commit_counts = queryfx_all(
        $conn_r,
        'SELECT repositoryID, count(*) N FROM %T
          GROUP BY repositoryID',
        $commit->getTableName());
      $commit_counts = ipull($commit_counts, 'N', 'repositoryID');
    }

    $rows = array();
    foreach ($repositories as $repository) {
      $id = $repository->getID();
      $commit = idx($commits, $id);
      $date = null;
      $time = null;
      if ($commit) {
        $date = date('M j, Y', $commit->getEpoch());
        $time = date('g:i A', $commit->getEpoch());
      }

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repository->getCallsign().'/',
          ),
          phutil_escape_html($repository->getName())),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        idx($commit_counts, $id, 0),
        $commit
          ? DiffusionView::linkCommit(
              $repository,
              $commit->getCommitIdentifier())
          : null,
        $date,
        $time,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Repository',
        'VCS',
        'Size',
        'Last',
        'Date',
        'Time',
      ));
    $table->setColumnClasses(
      array(
        'wide',
        '',
        'n',
        'n',
        '',
        'right',
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
