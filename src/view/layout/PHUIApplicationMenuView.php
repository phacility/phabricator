<?php

final class PHUIApplicationMenuView extends Phobject {

  private $viewer;
  private $crumbs;
  private $searchEngine;

  private $items = array();

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function addLabel($name) {
    $item = id(new PHUIListItemView())
      ->setName($name);

    return $this->addItem($item);
  }

  public function addLink($name, $href) {
    $item = id(new PHUIListItemView())
      ->setName($name)
      ->setHref($href);

    return $this->addItem($item);
  }

  public function addItem(PHUIListItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function setSearchEngine(PhabricatorApplicationSearchEngine $engine) {
    $this->searchEngine = $engine;
    return $this;
  }

  public function getSearchEngine() {
    return $this->searchEngine;
  }

  public function setCrumbs(PHUICrumbsView $crumbs) {
    $this->crumbs = $crumbs;
    return $this;
  }

  public function getCrumbs() {
    return $this->crumbs;
  }

  public function buildListView() {
    $viewer = $this->getViewer();

    $view = id(new PHUIListView())
      ->setUser($viewer);

    $crumbs = $this->getCrumbs();
    if ($crumbs) {
      $actions = $crumbs->getActions();
      if ($actions) {
        $view->newLabel(pht('Create'));
        foreach ($crumbs->getActions() as $action) {
          $view->addMenuItem($action);
        }
      }
    }

    $engine = $this->getSearchEngine();
    if ($engine) {
      $engine
        ->setViewer($viewer)
        ->addNavigationItems($view);
    }

    foreach ($this->items as $item) {
      $view->addMenuItem($item);
    }

    return $view;
  }

}
