<?php

final class ReleephProjectListController extends PhabricatorController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter', 'active');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new ReleephProjectQuery())
      ->setViewer($user)
      ->setOrder(ReleephProjectQuery::ORDER_NAME);

    switch ($this->filter) {
      case 'inactive':
        $query->withActive(0);
        $is_active = false;
        break;
      case 'active':
        $query->withActive(1);
        $is_active = true;
        break;
      default:
        throw new Exception("Unknown filter '{$this->filter}'!");
    }

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $releeph_projects = $query->executeWithCursorPager($pager);

    $releeph_projects_set = new LiskDAOSet();
    foreach ($releeph_projects as $releeph_project) {
      $releeph_projects_set->addToSet($releeph_project);
    }

    $panel = new AphrontPanelView();

    if ($is_active) {
      $view_inactive_link = phutil_tag(
        'a',
        array(
          'href'  => '/releeph/project/inactive/',
        ),
        'View inactive projects');
      $panel
        ->setHeader(hsprintf(
          'Active Releeph Projects &middot; %s', $view_inactive_link))
        ->appendChild(
          id(new ReleephActiveProjectListView())
            ->setUser($this->getRequest()->getUser())
            ->setReleephProjects($releeph_projects));
    } else {
      $view_active_link = phutil_tag(
        'a',
        array(
          'href' => '/releeph/project/'
        ),
        'View active projects');
      $panel
        ->setHeader(hsprintf(
          'Inactive Releeph Projects &middot; %s', $view_active_link))
        ->appendChild(
            id(new ReleephInactiveProjectListView())
              ->setUser($this->getRequest()->getUser())
              ->setReleephProjects($releeph_projects));
    }

    if ($is_active) {
      $create_new_project_button = phutil_tag(
        'a',
        array(
          'href'  => '/releeph/project/create/',
          'class' => 'green button',
        ),
        'Create New Project');
      $panel->addButton($create_new_project_button);
    }

    return $this->buildApplicationPage(
      array(
        $panel,
        $pager,
      ),
      array(
        'title' => 'List Releeph Projects',
      ));
  }

}
