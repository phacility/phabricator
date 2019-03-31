<?php

final class PhabricatorPeopleProfileTasksController
  extends PhabricatorPeopleProfileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfile(true)
      ->needProfileImage(true)
      ->needAvailability(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $class = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return new Aphront404Response();
    }

    $this->setUser($user);
    $title = array(pht('Assigned Tasks'), $user->getUsername());
    $header = $this->buildProfileHeader();
    $tasks = $this->buildTasksView($user);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Assigned Tasks'));
    $crumbs->setBorder(true);

    $nav = $this->newNavigation(
      $user,
      PhabricatorPeopleProfileMenuEngine::ITEM_TASKS);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setFooter(array(
        $tasks,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($view);
  }

  private function buildTasksView(PhabricatorUser $user) {
    $viewer = $this->getViewer();

    $open = ManiphestTaskStatus::getOpenStatusConstants();

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withOwners(array($user->getPHID()))
      ->withStatuses($open)
      ->needProjectPHIDs(true)
      ->setLimit(100)
      ->setGroupBy(ManiphestTaskQuery::GROUP_PRIORITY)
      ->execute();

    $handles = ManiphestTaskListView::loadTaskHandles($viewer, $tasks);

    $list = id(new ManiphestTaskListView())
      ->setUser($viewer)
      ->setHandles($handles)
      ->setTasks($tasks)
      ->setNoDataString(pht('No open, assigned tasks.'));

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Assigned Tasks'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);

    return $view;
  }
}
