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

class PhabricatorDirectoryMainController
  extends PhabricatorDirectoryController {

  private $filter;

  public function shouldRequireAdmin() {
    // These controllers are admin-only by default, but this one is public,
    // so allow non-admin users to view it.
    return false;
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();

    $project_query = new PhabricatorProjectQuery();
    $project_query->setMembers(array($user->getPHID()));
    $projects = $project_query->execute();

    $unbreak_panel = $this->buildUnbreakNowPanel();
    $triage_panel = $this->buildNeedsTriagePanel($projects);
    $revision_panel = $this->buildRevisionPanel();
    $tasks_panel = $this->buildTasksPanel();
    $feed_view = $this->buildFeedView($projects);

    $nav = $this->buildNav();
    $this->filter = $nav->selectFilter($this->filter, 'home');

    $content = array(
      $unbreak_panel,
      $triage_panel,
      $revision_panel,
      $tasks_panel,
      $feed_view,
    );

    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Directory',
        'tab'   => 'directory',
      ));
  }

  private function buildUnbreakNowPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $panel = new AphrontPanelView();
    $panel->setHeader('Unbreak Now!');
    $panel->setCaption('Open tasks with "Unbreak Now!" priority.');

    $task_query = new ManiphestTaskQuery();
    $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    $task_query->withPriority(ManiphestTaskPriority::PRIORITY_UNBREAK_NOW);
    $task_query->setLimit(10);
    $task_query->setCalculateRows(true);

    $tasks = $task_query->execute();

    if ($tasks) {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/view/all/',
            'class' => 'grey button',
          ),
          'View All Unbreak Now ('.$task_query->getRowCount().") \xC2\xBB"));

      $panel->appendChild($this->buildTaskListView($tasks));
    } else {
      $panel->appendChild(
        '<p>Nothing appears to be critically broken right now.</p>');
    }

    return $panel;
  }

  private function buildNeedsTriagePanel(array $projects) {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $panel = new AphrontPanelView();
    $panel->setHeader('Needs Triage');
    $panel->setCaption(
      'Open tasks with "Needs Triage" priority in '.
      '<a href="/project/">projects you are a member of</a>.');

    $task_query = new ManiphestTaskQuery();
    $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    $task_query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
    $task_query->withProjects(mpull($projects, 'getPHID'));
    $task_query->withAnyProject(true);
    $task_query->setCalculateRows(true);
    $task_query->setLimit(10);

    $tasks = $task_query->execute();
    if ($tasks) {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            // TODO: This should filter to just your projects' need-triage
            // tasks?
            'href' => '/maniphest/view/alltriage/',
            'class' => 'grey button',
          ),
          'View All Triage ('.$task_query->getRowCount().") \xC2\xBB"));
      $panel->appendChild($this->buildTaskListView($tasks));
    } else {
      $panel->appendChild('<p>No tasks in your projects need triage.</p>');
    }

    return $panel;
  }

  private function buildRevisionPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $revision_query = new DifferentialRevisionQuery();
    $revision_query->withStatus(DifferentialRevisionQuery::STATUS_OPEN);
    $revision_query->withResponsibleUsers(array($user_phid));
    $revision_query->needRelationships(true);

    // NOTE: We need to unlimit this query to hit the responsible user
    // fast-path.
    $revision_query->setLimit(null);
    $revisions = $revision_query->execute();

    list($active, $waiting) = DifferentialRevisionQuery::splitResponsible(
      $revisions,
      $user_phid);

    $panel = new AphrontPanelView();
    $panel->setHeader('Revisions Waiting on You');
    $panel->setCaption('Revisions waiting for you for review or commit.');

    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/differential/',
          'class' => 'button grey',
        ),
        "View Active Revisions \xC2\xBB"));

    if ($active) {
      $revision_view = id(new DifferentialRevisionListView())
        ->setRevisions($active)
        ->setUser($user);
      $phids = array_merge(
        array($user_phid),
        $revision_view->getRequiredHandlePHIDs());
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      $revision_view->setHandles($handles);

      $panel->appendChild($revision_view);
    } else {
      $panel->appendChild('<p>No revisions are waiting on you.</p>');
    }

    return $panel;
  }

  private function buildTasksPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $task_query = new ManiphestTaskQuery();
    $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    $task_query->withOwners(array($user_phid));
    $task_query->setCalculateRows(true);
    $task_query->setLimit(10);

    $tasks = $task_query->execute();

    $panel = new AphrontPanelView();
    $panel->setHeader('Assigned Tasks');
    $panel->setCaption('Tasks assigned to you.');

    if ($tasks) {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/',
            'class' => 'button grey',
          ),
          "View All Assigned Tasks (".$task_query->getRowCount().") \xC2\xBB"));
      $panel->appendChild($this->buildTaskListView($tasks));
    } else {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/?users='.
              ManiphestTaskOwner::OWNER_UP_FOR_GRABS,
            'class' => 'button grey',
          ),
          "View Unassigned Tasks \xC2\xBB"));
      $panel->appendChild('<p>You have no assigned tasks.</p>');
    }

    return $panel;
  }


  private function buildTaskListView(array $tasks) {
    $user = $this->getRequest()->getUser();

    $phids = array_filter(mpull($tasks, 'getOwnerPHID'));
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $view = new ManiphestTaskListView();
    $view->setTasks($tasks);
    $view->setUser($user);
    $view->setHandles($handles);

    return $view;
  }

  private function buildFeedView(array $projects) {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $feed_query = new PhabricatorFeedQuery();
    $feed_query->setFilterPHIDs(
      array_merge(
        array($user_phid),
        mpull($projects, 'getPHID')));
    $feed = $feed_query->execute();

    $builder = new PhabricatorFeedBuilder($feed);
    $builder->setUser($user);
    $feed_view = $builder->buildView();


    return
      '<div style="padding: 1em 1em;">'.
        '<h1 style="font-size: 18px; '.
                   'border-bottom: 1px solid #aaaaaa; '.
                   'margin: 0 1em;">Feed</h1>'.
        $feed_view->render().
      '</div>';
  }

}
