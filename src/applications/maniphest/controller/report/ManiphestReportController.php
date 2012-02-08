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
 * @group maniphest
 */
final class ManiphestReportController extends ManiphestController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/maniphest/report/'));
    $nav->addFilter('user',          'User');
    $nav->addFilter('project',       'Project');

    $this->view = $nav->selectFilter($this->view, 'user');

    $tasks = id(new ManiphestTaskQuery())
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->execute();

    $date = phabricator_date(time(), $user);

    switch ($this->view) {
      case 'user':
        $result = mgroup($tasks, 'getOwnerPHID');
        $leftover = idx($result, '', array());
        unset($result['']);
        $leftover_name = phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/?users=PHID-!!!!-UP-FOR-GRABS',
          ),
          'Up For Grabs');
        $col_header = 'User';
        $header = 'Open Tasks by User and Priority ('.$date.')';
        $link = '/maniphest/?users=';
        break;
      case 'project':
        $result = array();
        foreach ($tasks as $task) {
          $phids = $task->getProjectPHIDs();
          if ($phids) {
            foreach ($phids as $project_phid) {
              $result[$project_phid][] = $task;
            }
          } else {
            $leftover[] = $task;
          }
        }
        $leftover_name = 'Uncategorized';
        $col_header = 'Project';
        $header = 'Open Tasks by Project and Priority ('.$date.')';
        $link = '/maniphest/view/all/?projects=';
        break;
    }


    $phids = array_keys($result);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $handles = msort($handles, 'getName');

    $rows = array();
    $pri_total = array();
    foreach (array_merge($handles, array(null)) as $handle) {
      if ($handle) {
        $tasks = idx($result, $handle->getPHID(), array());
        $name = phutil_render_tag(
          'a',
          array(
            'href' => $link.$handle->getPHID(),
          ),
          phutil_escape_html($handle->getName()));
      } else {
        $tasks = $leftover;
        $name  = $leftover_name;
      }

      $tasks = mgroup($tasks, 'getPriority');

      $row = array();
      $row[] = $name;
      $total = 0;
      foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $label) {
        $n = count(idx($tasks, $pri, array()));
        if ($n == 0) {
          $row[] = '-';
        } else {
          $row[] = number_format($n);
        }
        $total += $n;
      }
      $row[] = number_format($total);

      $rows[] = $row;
    }

    $cname = array($col_header);
    $cclass = array('pri right wide');
    foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $label) {
      $cname[] = $label;
      $cclass[] = 'n';
    }
    $cname[] = 'Total';
    $cclass[] = 'n';

    $table = new AphrontTableView($rows);
    $table->setHeaders($cname);
    $table->setColumnClasses($cclass);

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($table);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Maniphest Reports',
      ));
  }

}
