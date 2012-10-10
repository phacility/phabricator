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

final class PhabricatorRepositoryListController
  extends PhabricatorRepositoryController {

  public function shouldRequireAdmin() {
    return false;
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $is_admin = $user->getIsAdmin();

    $repos = id(new PhabricatorRepository())->loadAll();

    $rows = array();
    foreach ($repos as $repo) {

      if ($repo->isTracked()) {
        $diffusion_link = phutil_render_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repo->getCallsign().'/',
          ),
          'View in Diffusion');
      } else {
        $diffusion_link = '<em>Not Tracked</em>';
      }

      $rows[] = array(
        phutil_escape_html($repo->getCallsign()),
        phutil_escape_html($repo->getName()),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repo->getVersionControlSystem()),
        $diffusion_link,
        phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/repository/edit/'.$repo->getID().'/',
          ),
          'Edit'),
        javelin_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/repository/delete/'.$repo->getID().'/',
            'sigil' => 'workflow',
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
        'Diffusion',
        '',
        ''
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        null,
        null,
        'action',
        'action',
      ));

    $table->setColumnVisibility(
      array(
        true,
        true,
        true,
        true,
        $is_admin,
        $is_admin,
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Repositories');
    if ($is_admin) {
      $panel->setCreateButton('Create New Repository', '/repository/create/');
    }
    $panel->appendChild($table);

    $projects = id(new PhabricatorRepositoryArcanistProject())->loadAll();

    $rows = array();
    foreach ($projects as $project) {
      $repo = idx($repos, $project->getRepositoryID());
      if ($repo) {
        $repo_name = phutil_escape_html($repo->getName());
      } else {
        $repo_name = '-';
      }

      $rows[] = array(
        phutil_escape_html($project->getName()),
        $repo_name,
        phutil_render_tag(
          'a',
          array(
            'href' => '/repository/project/edit/'.$project->getID().'/',
            'class' => 'button grey small',
          ),
          'Edit'),
        javelin_render_tag(
          'a',
          array(
            'href' => '/repository/project/delete/'.$project->getID().'/',
            'class' => 'button grey small',
            'sigil' => 'workflow',
          ),
          'Delete'),
      );

    }

    $project_table = new AphrontTableView($rows);
    $project_table->setHeaders(
      array(
        'Project ID',
        'Repository',
        '',
        '',
      ));
    $project_table->setColumnClasses(
      array(
        '',
        'wide',
        'action',
        'action',
      ));

    $project_table->setColumnVisibility(
      array(
        true,
        true,
        $is_admin,
        $is_admin,
      ));

    $project_panel = new AphrontPanelView();
    $project_panel->setHeader('Arcanist Projects');
    $project_panel->appendChild($project_table);

    return $this->buildStandardPageResponse(
      array(
        $this->renderDaemonNotice(),
        $panel,
        $project_panel,
      ),
      array(
        'title' => 'Repository List',
      ));
  }

}
