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

    $maniphest = 'PhabricatorApplicationManiphest';
    if (PhabricatorApplication::isClassInstalled($maniphest)) {
      $unbreak_panel = $this->buildUnbreakNowPanel();
      $triage_panel = $this->buildNeedsTriagePanel($projects);
      $tasks_panel = $this->buildTasksPanel();
    } else {
      $unbreak_panel = null;
      $triage_panel = null;
      $tasks_panel = null;
    }

    $audit = 'PhabricatorApplicationAudit';
    if (PhabricatorApplication::isClassInstalled($audit)) {
      $audit_panel = $this->buildAuditPanel();
      $commit_panel = $this->buildCommitPanel();
    } else {
      $audit_panel = null;
      $commit_panel = null;
    }

    if (PhabricatorEnv::getEnvConfig('welcome.html') !== null) {
      $welcome_panel = $this->buildWelcomePanel();
    } else {
      $welcome_panel = null;
    }

    $jump_panel = $this->buildJumpPanel();
    $revision_panel = $this->buildRevisionPanel();

    $content = array(
      $jump_panel,
      $welcome_panel,
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

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Phabricator',
        'device' => true,
      ));
  }

  private function buildJumpResponse() {
    $request = $this->getRequest();
    $jump = $request->getStr('jump');

    $response = PhabricatorJumpNavHandler::getJumpResponse(
      $request->getUser(),
      $jump);

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
    $unbreak_now = PhabricatorEnv::getEnvConfig(
      'maniphest.priorities.unbreak-now');
    if (!$unbreak_now) {
      return null;
    }

    $user = $this->getRequest()->getUser();

    $task_query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withStatuses(array(ManiphestTaskStatus::STATUS_OPEN))
      ->withPriorities(array($unbreak_now))
      ->setLimit(10);

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
      phutil_tag(
        'a',
        array(
          'href' => '/maniphest/?statuses[]=0&priorities[]='.$unbreak_now.'#R',
          'class' => 'grey button',
        ),
        "View All Unbreak Now \xC2\xBB"));

    $panel->appendChild($this->buildTaskListView($tasks));
    $panel->setNoBackground();

    return $panel;
  }

  private function buildNeedsTriagePanel(array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');

    $needs_triage = PhabricatorEnv::getEnvConfig(
      'maniphest.priorities.needs-triage');
    if (!$needs_triage) {
      return null;
    }

    $user = $this->getRequest()->getUser();
    if (!$user->isLoggedIn()) {
      return null;
    }

    if ($projects) {
      $task_query = id(new ManiphestTaskQuery())
        ->setViewer($user)
        ->withStatuses(array(ManiphestTaskStatus::STATUS_OPEN))
        ->withPriorities(array($needs_triage))
        ->withAnyProjects(mpull($projects, 'getPHID'))
        ->setLimit(10);
      $tasks = $task_query->execute();
    } else {
      $tasks = array();
    }

    if (!$tasks) {
      return $this->renderMiniPanel(
        'No "Needs Triage" Tasks',
        hsprintf(
          'No tasks in <a href="/project/">projects you are a member of</a> '.
          'need triage.'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Needs Triage');
    $panel->setCaption(hsprintf(
      'Open tasks with "Needs Triage" priority in '.
      '<a href="/project/">projects you are a member of</a>.'));

    $panel->addButton(
      phutil_tag(
        'a',
        array(
          'href' => '/maniphest/?statuses[]=0&priorities[]='.$needs_triage.
                    '&userProjects[]='.$user->getPHID().'#R',
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

    $revision_query = id(new DifferentialRevisionQuery())
      ->setViewer($user)
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->withResponsibleUsers(array($user_phid))
      ->needRelationships(true);

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
      phutil_tag(
        'a',
        array(
          'href' => '/differential/',
          'class' => 'button grey',
        ),
        "View Active Revisions \xC2\xBB"));

    $revision_view = id(new DifferentialRevisionListView())
      ->setHighlightAge(true)
      ->setRevisions(array_merge($blocking, $active))
      ->setFields(DifferentialRevisionListView::getDefaultFields($user))
      ->setUser($user)
      ->loadAssets();
    $phids = array_merge(
      array($user_phid),
      $revision_view->getRequiredHandlePHIDs());
    $handles = $this->loadViewerHandles($phids);

    $revision_view->setHandles($handles);

    $list_view = $revision_view->render();
    $list_view->setFlush(true);

    $panel->appendChild($list_view);
    $panel->setNoBackground();

    return $panel;
  }

  private function buildWelcomePanel() {
    $panel = new AphrontPanelView();
    $panel->appendChild(
      phutil_safe_html(
        PhabricatorEnv::getEnvConfig('welcome.html')));
    $panel->setNoBackground();

    return $panel;
  }

  private function buildTasksPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $task_query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setGroupBy(ManiphestTaskQuery::GROUP_PRIORITY)
      ->withOwners(array($user_phid))
      ->setLimit(10);

    $tasks = $task_query->execute();


    if (!$tasks) {
      return $this->renderMiniPanel(
        'No Assigned Tasks',
        'You have no assigned tasks.');
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Assigned Tasks');

    $panel->addButton(
      phutil_tag(
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
    $doc_link = phutil_tag(
      'a',
      array(
        'href' => $doc_href,
      ),
      'Jump Nav User Guide');

    $jump_input = phutil_tag(
      'input',
      array(
        'type'  => 'text',
        'class' => 'phabricator-jump-nav',
        'name'  => 'jump',
        'id'    => $uniq_id,
        'value' => $query,
      ));
    $jump_caption = phutil_tag(
      'p',
      array(
        'class' => 'phabricator-jump-nav-caption',
      ),
      hsprintf(
        'Enter the name of an object like <tt>D123</tt> to quickly jump to '.
          'it. See %s or type <tt>help</tt>.',
        $doc_link));

    $form = phabricator_form(
      $user,
      array(
        'action' => '/jump/',
        'method' => 'POST',
        'class'  => 'phabricator-jump-nav-form',
      ),
      array(
        $jump_input,
        $jump_caption,
      ));

    $panel = new AphrontPanelView();
    $panel->setNoBackground();
    // $panel->appendChild();

    $list_filter = new AphrontListFilterView();
    $list_filter->appendChild($form);

    $container = phutil_tag('div',
      array('class' => 'phabricator-jump-nav-container'),
      $list_filter);

    return $container;
  }

  private function renderMiniPanel($title, $body) {
    $panel = new AphrontMiniPanelView();
    $panel->appendChild(
      phutil_tag(
        'p',
        array(
        ),
        array(
          phutil_tag('strong', array(), $title.': '),
          $body
        )));
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
      phutil_tag(
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
    $query->withStatus(PhabricatorAuditCommitQuery::STATUS_CONCERN);
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
      phutil_tag(
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
