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

class PhabricatorProjectListController
  extends PhabricatorProjectController {

  public function processRequest() {

    $projects = id(new PhabricatorProject())->loadAllWhere(
      '1 = 1 ORDER BY id DESC limit 100');
    $project_phids = mpull($projects, 'getPHID');

    $profiles = array();
    if ($projects) {
      $profiles = id(new PhabricatorProjectProfile())->loadAllWhere(
        'projectPHID in (%Ls)',
        $project_phids);
      $profiles = mpull($profiles, null, 'getProjectPHID');
    }

    $affil_groups = array();
    if ($projects) {
      $affil_groups = PhabricatorProjectAffiliation::loadAllForProjectPHIDs(
        $project_phids);
    }

    $author_phids = mpull($projects, 'getAuthorPHID');
    $handles = id(new PhabricatorObjectHandleData($author_phids))
      ->loadHandles();

    $project_phids = mpull($projects, 'getPHID');

    $query = id(new ManiphestTaskQuery())
      ->withProjects($project_phids)
      ->withAnyProject(true)
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setLimit(PHP_INT_MAX);

    $tasks = $query->execute();
    $groups = array();
    foreach ($tasks as $task) {
      foreach ($task->getProjectPHIDs() as $phid) {
        $groups[$phid][] = $task;
      }
    }


    $rows = array();
    foreach ($projects as $project) {
      $phid = $project->getPHID();

      $profile = $profiles[$phid];
      $affiliations = $affil_groups[$phid];

      $group = idx($groups, $phid, array());
      $task_count = count($group);

      $population = count($affiliations);

      $status = PhabricatorProjectStatus::getNameForStatus(
        $project->getStatus());

      $blurb = $profile->getBlurb();
      $blurb = phutil_utf8_shorten($blurb, $columns = 100);

      $rows[] = array(
        phutil_escape_html($project->getName()),
        phutil_escape_html($blurb),
        $handles[$project->getAuthorPHID()]->renderLink(),
        phutil_escape_html($population),
        phutil_escape_html($status),
        phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/view/all/?projects='.$phid,
          ),
          phutil_escape_html($task_count)),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => '/project/view/'.$project->getID().'/',
          ),
          'View Project Profile'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Project',
        'Description',
        'Mastermind',
        'Population',
        'Status',
        'Open Tasks',
        '',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        'wide',
        '',
        'right',
        '',
        'right',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('Project');
    $panel->setCreateButton('Create New Project', '/project/create/');

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Projects',
      ));
  }
}
