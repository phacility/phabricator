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
    $request = $this->getRequest();
    $user = $request->getUser();

    $shortcuts = id(new PhabricatorRepositoryShortcut())->loadAll();
    if ($shortcuts) {
      $shortcuts = msort($shortcuts, 'getSequence');

      $rows = array();
      foreach ($shortcuts as $shortcut) {
        $rows[] = array(
          phutil_render_tag(
            'a',
            array(
              'href' => $shortcut->getHref(),
            ),
            phutil_escape_html($shortcut->getName())),
          phutil_escape_html($shortcut->getDescription()),
        );
      }

      $shortcut_table = new AphrontTableView($rows);
      $shortcut_table->setHeaders(
        array(
          'Link',
          '',
        ));
      $shortcut_table->setColumnClasses(
        array(
          'pri',
          'wide',
        ));

      $shortcut_panel = new AphrontPanelView();
      $shortcut_panel->setHeader('Shortcuts');
      $shortcut_panel->appendChild($shortcut_table);
    } else {
      $shortcut_panel = null;
    }

    $repository = new PhabricatorRepository();

    $repositories = $repository->loadAll();
    foreach ($repositories as $key => $repository) {
      if (!$repository->isTracked()) {
        unset($repositories[$key]);
      }
    }

    $repository_ids = mpull($repositories, 'getID');
    $summaries = array();
    $commits = array();
    if ($repository_ids) {
      $summaries = queryfx_all(
        $repository->establishConnection('r'),
        'SELECT * FROM %T WHERE repositoryID IN (%Ld)',
        PhabricatorRepository::TABLE_SUMMARY,
        $repository_ids);
      $summaries = ipull($summaries, null, 'repositoryID');

      $commit_ids = array_filter(ipull($summaries, 'lastCommitID'));
      if ($commit_ids) {
        $commit = new PhabricatorRepositoryCommit();
        $commits = $commit->loadAllWhere('id IN (%Ld)', $commit_ids);
        $commits = mpull($commits, null, 'getRepositoryID');
      }
    }

    $rows = array();
    foreach ($repositories as $repository) {
      $id = $repository->getID();
      $commit = idx($commits, $id);

      $size = idx(idx($summaries, $id, array()), 'size', 0);

      $date = '-';
      $time = '-';
      if ($commit) {
        $date = phabricator_date($commit->getEpoch(), $user);
        $time = phabricator_time($commit->getEpoch(), $user);
      }

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repository->getCallsign().'/',
          ),
          phutil_escape_html($repository->getName())),
        phutil_escape_html($repository->getDetail('description')),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        $size ? number_format($size) : '-',
        $commit
          ? DiffusionView::linkCommit(
              $repository,
              $commit->getCommitIdentifier())
          : '-',
        $date,
        $time,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Repository',
        'Description',
        'VCS',
        'Size',
        'Last',
        'Date',
        'Time',
      ));
    $table->setColumnClasses(
      array(
        'pri',
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
        $shortcut_panel,
        $panel,
      ),
      array(
        'title' => 'Diffusion',
      ));
  }

}
