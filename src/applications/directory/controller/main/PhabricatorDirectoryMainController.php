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
  private $subfilter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
    $this->subfilter = idx($data, 'subfilter');
  }

  public function shouldRequireAdmin() {
    // These controllers are admin-only by default, but this one is public,
    // so allow non-admin users to view it.
    return false;
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();

    $nav = $this->buildNav();
    $this->filter = $nav->selectFilter($this->filter, 'home');

    switch ($this->filter) {
      case 'jump':
        break;
      case 'home':
      case 'feed':
        $project_query = new PhabricatorProjectQuery();
        $project_query->setMembers(array($user->getPHID()));
        $projects = $project_query->execute();
        break;
      default:
        throw new Exception("Unknown filter '{$this->filter}'!");
    }

    switch ($this->filter) {
      case 'feed':
        return $this->buildFeedResponse($nav, $projects);
      case 'jump':
        return $this->buildJumpResponse($nav);
      default:
        return $this->buildMainResponse($nav, $projects);
    }

  }

  private function buildMainResponse($nav, $projects) {
    if (PhabricatorEnv::getEnvConfig('maniphest.enabled')) {
      $unbreak_panel = $this->buildUnbreakNowPanel();
      $triage_panel = $this->buildNeedsTriagePanel($projects);
      $tasks_panel = $this->buildTasksPanel();
    } else {
      $unbreak_panel = null;
      $triage_panel = null;
      $tasks_panel = null;
    }
    $jump_panel = $this->buildJumpPanel();
    $revision_panel = $this->buildRevisionPanel();

    $content = array(
      $unbreak_panel,
      $triage_panel,
      $jump_panel,
      $revision_panel,
      $tasks_panel,
    );

    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Phabricator',
      ));
  }

  private function buildJumpResponse($nav) {
    $request = $this->getRequest();

    if ($request->isFormPost()) {
      $jump = $request->getStr('jump');
      $jump = trim($jump);

      $help_href = PhabricatorEnv::getDocLink(
        'article/Jump_Nav_User_Guide.html');

      $patterns = array(
        '/^help/i'                  => 'uri:'.$help_href,
        '/^d$/i'                    => 'uri:/differential/',
        '/^r$/i'                    => 'uri:/diffusion/',
        '/^t$/i'                    => 'uri:/maniphest/',
        '/^p$/i'                    => 'uri:/project/',
        '/^u$/i'                    => 'uri:/people/',
        '/r([A-Z]+)$/'              => 'repository',
        '/r([A-Z]+)(\S+)$/'         => 'commit',
        '/^d(\d+)$/i'               => 'revision',
        '/^t(\d+)$/i'               => 'task',
        '/^p\s+(.+)$/i'            => 'project',
        '/^u\s+(\S+)$/i'            => 'user',
        '/^task:\s*(.+)/i'         => 'create-task',
        '/^(?:s|symbol)\s+(\S+)/i'  => 'find-symbol',
      );


      foreach ($patterns as $pattern => $effect) {
        $matches = null;
        if (preg_match($pattern, $jump, $matches)) {
          if (!strncmp($effect, 'uri:', 4)) {
            return id(new AphrontRedirectResponse())
              ->setURI(substr($effect, 4));
          } else {
            switch ($effect) {
              case 'repository':
                return id(new AphrontRedirectResponse())
                  ->setURI('/diffusion/'.$matches[1].'/');
              case 'commit':
                return id(new AphrontRedirectResponse())
                  ->setURI('/'.$matches[0]);
              case 'revision':
                return id(new AphrontRedirectResponse())
                  ->setURI('/D'.$matches[1]);
              case 'task':
                return id(new AphrontRedirectResponse())
                  ->setURI('/T'.$matches[1]);
              case 'user':
                return id(new AphrontRedirectResponse())
                  ->setURI('/p/'.$matches[1].'/');
              case 'project':
                $project = PhabricatorProjectQueryUtil
                  ::findCloselyNamedProject($matches[1]);
                if ($project) {
                  return id(new AphrontRedirectResponse())
                    ->setURI('/project/view/'.$project->getID().'/');
                } else {
                    $jump = $matches[1];
                }
                break;
              case 'find-symbol':
                return id(new AphrontRedirectResponse())
                  ->setURI('/diffusion/symbol/'.$matches[1].'/?jump=true');
              case 'create-task':
                return id(new AphrontRedirectResponse())
                  ->setURI('/maniphest/task/create/?title='
                    .phutil_escape_uri($matches[1]));
              default:
                throw new Exception("Unknown jump effect '{$effect}'!");
            }
          }
        }
      }

      $query = new PhabricatorSearchQuery();
      $query->setQuery($jump);
      $query->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/search/'.$query->getQueryKey().'/');
    }


    $nav->appendChild($this->buildJumpPanel());
    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Jump Nav',
      ));
  }

  private function buildFeedResponse($nav, $projects) {

    $subnav = new AphrontSideNavFilterView();
    $subnav->setBaseURI(new PhutilURI('/feed/'));

    $subnav->addFilter('all',       'All Activity', '/feed/');
    $subnav->addFilter('projects',  'My Projects');

    $filter = $subnav->selectFilter($this->subfilter, 'all');

    switch ($filter) {
      case 'all':
        $phids = array();
        break;
      case 'projects':
        $phids = mpull($projects, 'getPHID');
        break;
    }

    $view = $this->buildFeedView($phids);
    $subnav->appendChild($view);

    $nav->appendChild($subnav);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Feed',
      ));
  }

  private function buildUnbreakNowPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $task_query = new ManiphestTaskQuery();
    $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    $task_query->withPriority(ManiphestTaskPriority::PRIORITY_UNBREAK_NOW);
    $task_query->setLimit(10);
    $task_query->setCalculateRows(true);

    $tasks = $task_query->execute();

    if ($tasks) {
      $panel = new AphrontPanelView();
      $panel->setHeader('Unbreak Now!');
      $panel->setCaption('Open tasks with "Unbreak Now!" priority.');
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
      $panel = new AphrontMiniPanelView();
      $panel->appendChild(
        '<p><strong>No "Unbreak Now!" Tasks:</strong> '.
        'Nothing appears to be critically broken right now.</p>');
      $panel = '<br />'.$panel->render();
    }

    return $panel;
  }

  private function buildNeedsTriagePanel(array $projects) {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    if ($projects) {
      $task_query = new ManiphestTaskQuery();
      $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
      $task_query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
      $task_query->withProjects(mpull($projects, 'getPHID'));
      $task_query->withAnyProject(true);
      $task_query->setCalculateRows(true);
      $task_query->setLimit(10);
      $tasks = $task_query->execute();
    } else {
      $tasks = array();
    }

    if ($tasks) {
      $panel = new AphrontPanelView();
      $panel->setHeader('Needs Triage');
      $panel->setCaption(
        'Open tasks with "Needs Triage" priority in '.
        '<a href="/project/">projects you are a member of</a>.');

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
      $panel = new AphrontMiniPanelView();
      $panel->appendChild(
        '<p><strong>No "Needs Triage" Tasks:</strong> '.
        'No tasks in <a href="/project/">projects you are a member of</a> '.
        'need triage.</p>');
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
      $fields =

      $revision_view = id(new DifferentialRevisionListView())
        ->setRevisions($active)
        ->setFields(DifferentialRevisionListView::getDefaultFields())
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
    $task_query->setGroupBy(ManiphestTaskQuery::GROUP_PRIORITY);
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

  private function buildFeedView(array $phids) {
    $request = $this->getRequest();
    $user = $request->getUser();
    $user_phid = $user->getPHID();

    $feed_query = new PhabricatorFeedQuery();
    if ($phids) {
      $feed_query->setFilterPHIDs($phids);
    }

    // TODO: All this limit stuff should probably be consolidated into the
    // feed query?

    $old_link = null;
    $new_link = null;

    $feed_query->setAfter($request->getStr('after'));
    $feed_query->setBefore($request->getStr('before'));
    $limit = 500;

    // Grab one more story than we intend to display so we can figure out
    // if we need to render an "Older Posts" link or not (with reasonable
    // accuracy, at least).
    $feed_query->setLimit($limit + 1);
    $feed = $feed_query->execute();
    $extra_row = (count($feed) == $limit + 1);

    $have_new = ($request->getStr('before')) ||
                ($request->getStr('after') && $extra_row);

    $have_old = ($request->getStr('after')) ||
                ($request->getStr('before') && $extra_row) ||
                (!$request->getStr('before') &&
                 !$request->getStr('after') &&
                 $extra_row);
    $feed = array_slice($feed, 0, $limit, $preserve_keys = true);

    if ($have_old) {
      $old_link = phutil_render_tag(
        'a',
        array(
          'href' => '?before='.end($feed)->getChronologicalKey(),
          'class' => 'phabricator-feed-older-link',
        ),
        "Older Stories \xC2\xBB");
    }
    if ($have_new) {
      $new_link = phutil_render_tag(
        'a',
        array(
          'href' => '?after='.reset($feed)->getChronologicalKey(),
          'class' => 'phabricator-feed-newer-link',
        ),
        "\xC2\xAB Newer Stories");
    }

    $builder = new PhabricatorFeedBuilder($feed);
    $builder->setUser($user);
    $feed_view = $builder->buildView();

    return
      '<div style="padding: 1em 3em;">'.
        '<div style="margin: 0 1em;">'.
          '<h1 style="font-size: 18px; '.
                     'border-bottom: 1px solid #aaaaaa; '.
                     'padding: 0;">Feed</h1>'.
        '</div>'.
        $feed_view->render().
        '<div class="phabricator-feed-frame">'.
          $new_link.
          $old_link.
        '</div>'.
      '</div>';
  }

  private function buildJumpPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $uniq_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'phabricator-autofocus',
      array(
        'id' => $uniq_id,
      ));

    require_celerity_resource('phabricator-jump-nav');

    $doc_href = PhabricatorEnv::getDocLink('article/Jump_Nav_User_Guide.html');
    $doc_link = phutil_render_tag(
      'a',
      array(
        'href' => $doc_href,
      ),
      'Jump Nav User Guide');

    $jump_input = phutil_render_tag(
      'input',
      array(
        'type'  => 'text',
        'class' => 'phabricator-jump-nav',
        'name'  => 'jump',
        'id'    => $uniq_id,
      )).
      phutil_render_tag(
        'p',
        array(
          'class' => 'phabricator-jump-nav-caption',
        ),
        'Enter the name of an object like <tt>D123</tt> to quickly jump to '.
        'it. See '.$doc_link.' or type <tt>help</tt>.');

    $panel = new AphrontPanelView();
    $panel->appendChild(
      phabricator_render_form(
        $user,
        array(
          'action' => '/jump/',
          'method' => 'POST',
        ),
        $jump_input));

    $nav_buttons = array();

    if (PhabricatorEnv::getEnvConfig('maniphest.enabled')) {
      $nav_buttons['/maniphest/task/create/'] = 'Create a Task';
    }
    $nav_buttons['/file/'] = 'Upload a File';
    $nav_buttons['/paste/'] = 'Create Paste';
    if (PhabricatorEnv::getEnvConfig('phriction.enabled')) {
      $nav_buttons['/w/'] = 'Browse Wiki';
    }
    $nav_buttons['/diffusion/'] = 'Browse Code';

    $panel->appendChild('<div class="phabricator-jump-nav-buttons">');
    foreach ($nav_buttons as $uri => $name) {
      $panel->appendChild(
        phutil_render_tag(
          'a',
          array(
            'href' => $uri,
            'class' => 'button grey',
          ),
          phutil_escape_html($name)));
    }
    $panel->appendChild('</div>');

    return $panel;
  }

}
