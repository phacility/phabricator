<?php

final class PhabricatorHomeMainController extends PhabricatorHomeController {

  private $minipanels = array();

  public function shouldAllowPublic() {
    return true;
  }

  public function isGlobalDragAndDropUploadEnabled() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $dashboard = PhabricatorDashboardInstall::getDashboard(
      $user,
      $user->getPHID(),
      get_class($this->getCurrentApplication()));

    if (!$dashboard) {
      $dashboard = PhabricatorDashboardInstall::getDashboard(
        $user,
        PhabricatorHomeApplication::DASHBOARD_DEFAULT,
        get_class($this->getCurrentApplication()));
    }

    if ($dashboard) {
      $content = id(new PhabricatorDashboardRenderingEngine())
        ->setViewer($user)
        ->setDashboard($dashboard)
        ->renderDashboard();
    } else {
      $project_query = new PhabricatorProjectQuery();
      $project_query->setViewer($user);
      $project_query->withMemberPHIDs(array($user->getPHID()));
      $projects = $project_query->execute();

      $content = $this->buildMainResponse($projects);
    }

    if (!$request->getURIData('only')) {
      $nav = $this->buildNav();
      $nav->appendChild(
        array(
          $content,
          id(new PhabricatorGlobalUploadTargetView())->setUser($user),
        ));
      $content = $nav;
    }

    return $this->newPage()
      ->setTitle('Phabricator')
      ->appendChild($content);

  }

  private function buildMainResponse(array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');
    $viewer = $this->getRequest()->getUser();

    $has_maniphest = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorManiphestApplication',
      $viewer);

    $has_audit = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorAuditApplication',
      $viewer);

    $has_differential = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);

    if ($has_maniphest) {
      $unbreak_panel = $this->buildUnbreakNowPanel();
      $triage_panel = $this->buildNeedsTriagePanel($projects);
      $tasks_panel = $this->buildTasksPanel();
    } else {
      $unbreak_panel = null;
      $triage_panel = null;
      $tasks_panel = null;
    }

    if ($has_audit) {
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

    if ($has_differential) {
      $revision_panel = $this->buildRevisionPanel();
    } else {
      $revision_panel = null;
    }

    $home = phutil_tag(
      'div',
      array(
        'class' => 'homepage-panel',
      ),
      array(
        $welcome_panel,
        $unbreak_panel,
        $triage_panel,
        $revision_panel,
        $tasks_panel,
        $audit_panel,
        $commit_panel,
        $this->minipanels,
      ));
      return $home;
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
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->withPriorities(array($unbreak_now))
      ->needProjectPHIDs(true)
      ->setLimit(10);

    $tasks = $task_query->execute();

    if (!$tasks) {
      return $this->renderMiniPanel(
        pht('No "Unbreak Now!" Tasks'),
        pht('Nothing appears to be critically broken right now.'));
    }

    $href = urisprintf(
      '/maniphest/?statuses=open()&priorities=%s#R',
      $unbreak_now);
    $title = pht('Unbreak Now!');
    $panel = new PHUIObjectBoxView();
    $panel->setHeader($this->renderSectionHeader($title, $href));
    $panel->setObjectList($this->buildTaskListView($tasks));

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
        ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
        ->withPriorities(array($needs_triage))
        ->withEdgeLogicPHIDs(
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          PhabricatorQueryConstraint::OPERATOR_OR,
          mpull($projects, 'getPHID'))
        ->needProjectPHIDs(true)
        ->setLimit(10);
      $tasks = $task_query->execute();
    } else {
      $tasks = array();
    }

    if (!$tasks) {
      return $this->renderMiniPanel(
        pht('No "Needs Triage" Tasks'),
        pht('No tasks tagged with projects you are a member of need triage.'));
    }

    $title = pht('Needs Triage');
    $href = urisprintf(
      '/maniphest/?statuses=open()&priorities=%s&projects=projects(%s)#R',
      $needs_triage,
      $user->getPHID());
    $panel = new PHUIObjectBoxView();
    $panel->setHeader($this->renderSectionHeader($title, $href));
    $panel->setObjectList($this->buildTaskListView($tasks));

    return $panel;
  }

  private function buildRevisionPanel() {
    $viewer = $this->getViewer();

    $revisions = PhabricatorDifferentialApplication::loadNeedAttentionRevisions(
      $viewer);

    if (!$revisions) {
      return $this->renderMiniPanel(
        pht('No Waiting Revisions'),
        pht('No revisions are waiting on you.'));
    }

    $title = pht('Revisions Waiting on You');
    $href = '/differential/';
    $panel = new PHUIObjectBoxView();
    $panel->setHeader($this->renderSectionHeader($title, $href));

    $revision_view = id(new DifferentialRevisionListView())
      ->setHighlightAge(true)
      ->setRevisions($revisions)
      ->setUser($viewer);

    $phids = array_merge(
      array($viewer->getPHID()),
      $revision_view->getRequiredHandlePHIDs());
    $handles = $this->loadViewerHandles($phids);

    $revision_view->setHandles($handles);

    $list_view = $revision_view->render();

    $panel->setObjectList($list_view);

    return $panel;
  }

  private function buildWelcomePanel() {
    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Welcome'));
    $panel->appendChild(
      phutil_safe_html(
        PhabricatorEnv::getEnvConfig('welcome.html')));

    return $panel;
  }

  private function buildTasksPanel() {
    $user = $this->getRequest()->getUser();
    $user_phid = $user->getPHID();

    $task_query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->setGroupBy(ManiphestTaskQuery::GROUP_PRIORITY)
      ->withOwners(array($user_phid))
      ->needProjectPHIDs(true)
      ->setLimit(10);

    $tasks = $task_query->execute();


    if (!$tasks) {
      return $this->renderMiniPanel(
        pht('No Assigned Tasks'),
        pht('You have no assigned tasks.'));
    }

    $title = pht('Assigned Tasks');
    $href = '/maniphest/query/assigned/';
    $panel = new PHUIObjectBoxView();
    $panel->setHeader($this->renderSectionHeader($title, $href));
    $panel->setObjectList($this->buildTaskListView($tasks));

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

  private function renderSectionHeader($title, $href) {
    $title = phutil_tag(
      'a',
      array(
        'href' => $href,
      ),
      $title);
    $icon = id(new PHUIIconView())
      ->setIcon('fa-search')
      ->setHref($href);
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->addActionItem($icon);
    return $header;
  }

  private function renderMiniPanel($title, $body) {
    $panel = new PHUIInfoView();
    $panel->setSeverity(PHUIInfoView::SEVERITY_NODATA);
    $panel->appendChild(
      phutil_tag(
        'p',
        array(
        ),
        array(
          phutil_tag('strong', array(), $title.': '),
          $body,
        )));
    $this->minipanels[] = $panel;
  }

  public function buildAuditPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $query = id(new DiffusionCommitQuery())
      ->setViewer($user)
      ->withNeedsAuditByPHIDs($phids)
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_OPEN)
      ->needAuditRequests(true)
      ->needCommitData(true)
      ->setLimit(10);

    $commits = $query->execute();

    if (!$commits) {
      return $this->renderMinipanel(
        pht('No Audits'),
        pht('No commits are waiting for you to audit them.'));
    }

    $view = id(new PhabricatorAuditListView())
      ->setCommits($commits)
      ->setUser($user);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $title = pht('Audits');
    $href = '/audit/';
    $panel = new PHUIObjectBoxView();
    $panel->setHeader($this->renderSectionHeader($title, $href));
    $panel->setObjectList($view);

    return $panel;
  }

  public function buildCommitPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = array($user->getPHID());

    $query = id(new DiffusionCommitQuery())
      ->setViewer($user)
      ->withAuthorPHIDs($phids)
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_CONCERN)
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->setLimit(10);

    $commits = $query->execute();

    if (!$commits) {
      return $this->renderMinipanel(
        pht('No Problem Commits'),
        pht('No one has raised concerns with your commits.'));
    }

    $view = id(new PhabricatorAuditListView())
      ->setCommits($commits)
      ->setUser($user);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $title = pht('Problem Commits');
    $href = '/audit/';
    $panel = new PHUIObjectBoxView();
    $panel->setHeader($this->renderSectionHeader($title, $href));
    $panel->setObjectList($view);

    return $panel;
  }

}
