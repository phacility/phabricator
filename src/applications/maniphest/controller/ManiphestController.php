<?php

abstract class ManiphestController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  public function buildSideNavView() {
    $viewer = $this->getViewer();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    if ($viewer->isLoggedIn()) {
      // For now, don't give logged-out users access to reports.
      $nav->addLabel(pht('Reports'));
      $nav->addFilter('report', pht('Reports'));
    }

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new ManiphestEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

  public function renderSingleTask(ManiphestTask $task) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = $task->getProjectPHIDs();
    if ($task->getOwnerPHID()) {
      $phids[] = $task->getOwnerPHID();
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs($phids)
      ->execute();

    $view = id(new ManiphestTaskListView())
      ->setUser($user)
      ->setShowSubpriorityControls(!$request->getStr('ungrippable'))
      ->setShowBatchControls(true)
      ->setHandles($handles)
      ->setTasks(array($task));

    return $view;
  }

}
