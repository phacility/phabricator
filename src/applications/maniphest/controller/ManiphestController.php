<?php

abstract class ManiphestController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
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

  final protected function newTaskGraphDropdownMenu(
    ManiphestTask $task,
    $has_parents,
    $has_subtasks,
    $include_standalone) {
    $viewer = $this->getViewer();

    $parents_uri = urisprintf(
      '/?subtaskIDs=%d#R',
      $task->getID());
    $parents_uri = $this->getApplicationURI($parents_uri);

    $subtasks_uri = urisprintf(
      '/?parentIDs=%d#R',
      $task->getID());
    $subtasks_uri = $this->getApplicationURI($subtasks_uri);

    $dropdown_menu = id(new PhabricatorActionListView())
      ->setViewer($viewer)
      ->addAction(
        id(new PhabricatorActionView())
          ->setHref($parents_uri)
          ->setName(pht('Search Parent Tasks'))
          ->setDisabled(!$has_parents)
          ->setIcon('fa-chevron-circle-up'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setHref($subtasks_uri)
          ->setName(pht('Search Subtasks'))
          ->setDisabled(!$has_subtasks)
          ->setIcon('fa-chevron-circle-down'));

    if ($include_standalone) {
      $standalone_uri = urisprintf('/graph/%d/', $task->getID());
      $standalone_uri = $this->getApplicationURI($standalone_uri);

      $dropdown_menu->addAction(
        id(new PhabricatorActionView())
          ->setHref($standalone_uri)
          ->setName(pht('View Standalone Graph'))
          ->setIcon('fa-code-fork'));
    }

    $graph_menu = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-search')
      ->setText(pht('Search...'))
      ->setDropdownMenu($dropdown_menu);

    return $graph_menu;
  }

  final protected function newTaskGraphOverflowView(
    ManiphestTask $task,
    $overflow_message,
    $include_standalone) {

    $id = $task->getID();

    if ($include_standalone) {
      $standalone_uri = $this->getApplicationURI("graph/{$id}/");

      $standalone_link = id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($standalone_uri)
        ->setColor(PHUIButtonView::GREY)
        ->setIcon('fa-code-fork')
        ->setText(pht('View Standalone Graph'));
    } else {
      $standalone_link = null;
    }

    $standalone_icon = id(new PHUIIconView())
      ->setIcon('fa-exclamation-triangle', 'yellow')
      ->addClass('object-graph-header-icon');

    $standalone_view = phutil_tag(
      'div',
      array(
        'class' => 'object-graph-header',
      ),
      array(
        $standalone_link,
        $standalone_icon,
        phutil_tag(
          'div',
          array(
            'class' => 'object-graph-header-message',
          ),
          array(
            $overflow_message,
          )),
      ));

    return $standalone_view;
  }


}
