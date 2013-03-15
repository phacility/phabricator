<?php

final class ReleephProjectListController extends PhabricatorController {

  public function processRequest() {
    $path = $this->getRequest()->getRequestURI()->getPath();
    $is_active = strpos($path, 'inactive/') === false;

    $releeph_projects = mfilter(
      id(new ReleephProject())->loadAll(),
      'getIsActive',
      !$is_active);
    $releeph_projects = msort($releeph_projects, 'getName');

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

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'List Releeph Projects'
      ));
  }

}
