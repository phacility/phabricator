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

class PhabricatorRepositoryListController extends PhabricatorController {


  public function processRequest() {

    $repos = id(new PhabricatorRepository())->loadAll();

    $rows = array();
    foreach ($repos as $repo) {
      $rows[] = array(
        phutil_escape_html($repo->getCallsign()),
        phutil_escape_html($repo->getName()),
        $repo->getVersionControlSystem(),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/repository/edit/'.$repo->getID().'/',
          ),
          'Edit'),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/repository/delete/'.$repo->getID().'/',
          ),
          'Delete'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Callsign',
        'Repository',
        'Type',
        '',
        ''
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        null,
        'action',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Repositories');
    $panel->setCreateButton('Create New Repository', '/repository/create/');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Repository List',
      ));
  }

}
