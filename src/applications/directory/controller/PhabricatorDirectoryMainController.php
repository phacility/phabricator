<?php

final class PhabricatorDirectoryMainController
  extends PhabricatorDirectoryController {

  private $filter;
  private $minipanels = array();

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();

    if ($this->filter == 'jump') {
      return $this->buildJumpResponse();
    }

    $nav = $this->buildNav();

    $project_query = new PhabricatorProjectQuery();
    $project_query->setViewer($user);
    $project_query->withMemberPHIDs(array($user->getPHID()));
    $projects = $project_query->execute();

    return $this->buildMainResponse($nav, $projects);
  }

  private function buildMainResponse($nav, array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');

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
    $audit_panel = $this->buildAuditPanel();
    $commit_panel = $this->buildCommitPanel();

    $content = array(
      $jump_panel,
      $unbreak_panel,
      $triage_panel,
      $revision_panel,
      $tasks_panel,
      $audit_panel,
      $commit_panel,
      $this->minipanels,
    );

    $nav->appendChild($content);
    $nav->appendChild(new PhabricatorGlobalUploadTargetView());

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Phabricator',
      ));
  }

  private function buildJumpResponse() {
    $request = $this->getRequest();

    $jump = $request->getStr('jump');

    $response = PhabricatorJumpNavHandler::jumpPostResponse($jump);

    if ($response) {

      return $response;
    } else if ($request->isFormPost()) {
      $query = new PhabricatorSearchQuery();
      $query->setQuery($jump);
      $query->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/search/'.$query->getQueryKey().'/');
    } else {
      return id(new AphrontRedirectResponse())->setURI('/');
    }
  }

  private function buildUnbreakNowPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $task_query = new ManiphestTaskQuery();
    $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    $task_query->withPriority(ManiphestTaskPriority::PRIORITY_UNBREAK_NOW);
    $task_query->setLimit(10);

    $tasks = $task_query->execute();

    if (!$tasks) {
      return $this->renderMiniPanel(
        'No "Unbreak Now!" Tasks',
        'Nothing appears to be critically broken right now.');
    }

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
        "View All Unbreak Now \xC2\xBB"));

    $panel->appendChild($this->buildTaskListView($tasks));
    $panel->setNoBackground();

    return $panel;
  }

  private function buildNeedsTriagePanel(array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');

    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    if ($projects) {
      $task_query = new ManiphestTaskQuery();
      $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
      $task_query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
      $task_query->withAnyProjects(mpull($projects, 'getPHID'));
      $task_query->setLimit(10);
      $tasks = $task_query->execute();
    } else {
      $tasks = array();
    }

    if (!$tasks) {
      return $this->renderMiniPanel(
        'No "Needs Triage" Tasks',
        'No tasks in <a href="/project/">projects you are a member of</a> '.
        'need triage.');
    }

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
          'href' => '/maniphest/view/projecttriage/',
          'class' => 'grey button',
        ),
        "View All Triage \xC2\xBB"));
    $panel->appendChild($this->buildTaskListView($tasks));
    $panel->setNoBackground();

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

    list($blocking, $active, ) = DifferentialRevisionQuery::splitResponsible(
        $revisions,
        array($user_phid));

    if (!$blocking && !$active) {
      return $this->renderMiniPanel(
        'No Waiting Revisions',
        'No revisions are waiting on you.');
    }

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

    $revision_view = id(new DifferentialRevisionListView())
      ->setHighlightAge(true)
      ->setRevisions(array_merge($blocking, $active))
      ->setFields(DifferentialRevisionListView::getDefaultFields())
      ->setUser($user)
      ->loadAssets();
    $phids = array_merge(
      array($user_phid),
      $revision_view->getRequiredHandlePHIDs());
    $handles = $this->loadViewerHandles($phids);

    $revision_view->setHandles($handles);

    $panel->appendChild($revision_view);
    $panel->setNoBackground();

    return $panel;
  }

  private function buildTasksPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $task_query = new ManiphestTaskQuery();
    $task_query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    $task_query->setGroupBy(ManiphestTaskQuery::GROUP_PRIORITY);
    $task_query->withOwners(array($user_phid));
    $task_query->setLimit(10);

    $tasks = $task_query->execute();


    if (!$tasks) {
      return $this->renderMiniPanel(
        'No Assigned Tasks',
        'You have no assigned tasks.');
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Assigned Tasks');

    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/maniphest/',
          'class' => 'button grey',
        ),
        "View Active Tasks \xC2\xBB"));
    $panel->appendChild($this->buildTaskListView($tasks));
    $panel->setNoBackground();

    return $panel;
  }

  private function buildTaskListView(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');
    $user = $this->getRequest()->getUser();

    $phids = array_merge(
      array_filter(mpull($tasks, 'getOwnerPHID')),
      array_mergev(mpull($tasks, 'getProjectPHIDs')));

    $handles = $this->loadViewerHandles($phids);

    $view = new ManiphestTaskListView();
    $view->setTasks($tasks);
    $view->setUser($user);
    $view->setHandles($handles);

    return $view;
  }

  private function buildJumpPanel($query=null) {
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
        'value' => $query,
      ));
    $jump_caption = phutil_render_tag(
      'p',
      array(
        'class' => 'phabricator-jump-nav-caption',
      ),
      'Enter the name of an object like <tt>D123</tt> to quickly jump to '.
      'it. See '.$doc_link.' or type <tt>help</tt>.');

    $panel = new AphrontPanelView();
    $panel->setHeader('Jump Nav');
    $panel->appendChild(
      phabricator_render_form(
        $user,
        array(
          'action' => '/jump/',
          'method' => 'POST',
          'class'  => 'phabricator-jump-nav-form',
        ),
        $jump_input.
        $jump_caption));

    return $panel;
  }

  private function renderMiniPanel($title, $body) {
    $panel = new AphrontMiniPanelView();
    $panel->appendChild(
      phutil_render_tag(
        'p',
        array(
        ),
        '<strong>'.$title.':</strong> '.$body));
    $this->minipanels[] = $panel;
  }

  public function buildAuditPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $query = new PhabricatorAuditQuery();
    $query->withAuditorPHIDs($phids);
    $query->withStatus(PhabricatorAuditQuery::STATUS_OPEN);
    $query->withAwaitingUser($user);
    $query->needCommitData(true);
    $query->setLimit(10);

    $audits = $query->execute();
    $commits = $query->getCommits();

    if (!$audits) {
      return $this->renderMinipanel(
        'No Audits',
        'No commits are waiting for you to audit them.');
    }

    $view = new PhabricatorAuditListView();
    $view->setAudits($audits);
    $view->setCommits($commits);
    $view->setUser($user);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader('Audits');
    $panel->setCaption('Commits awaiting your audit.');
    $panel->appendChild($view);
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/audit/',
          'class' => 'button grey',
        ),
        "View Active Audits \xC2\xBB"));
    $panel->setNoBackground();

    return $panel;
  }

  public function buildCommitPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = array($user->getPHID());

    $query = new PhabricatorAuditCommitQuery();
    $query->withAuthorPHIDs($phids);
    $query->withStatus(PhabricatorAuditQuery::STATUS_OPEN);
    $query->needCommitData(true);
    $query->setLimit(10);

    $commits = $query->execute();

    if (!$commits) {
      return $this->renderMinipanel(
        'No Problem Commits',
        'No one has raised concerns with your commits.');
    }

    $view = new PhabricatorAuditCommitListView();
    $view->setCommits($commits);
    $view->setUser($user);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader('Problem Commits');
    $panel->setCaption('Commits which auditors have raised concerns about.');
    $panel->appendChild($view);
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/audit/',
          'class' => 'button grey',
        ),
        "View Problem Commits \xC2\xBB"));
    $panel->setNoBackground();

    return $panel;
  }

}
