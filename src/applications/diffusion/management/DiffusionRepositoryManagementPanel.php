<?php

abstract class DiffusionRepositoryManagementPanel
  extends Phobject {

  private $viewer;
  private $repository;
  private $controller;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  final public function getRepository() {
    return $this->repository;
  }

  final public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  final public function getManagementPanelKey() {
    return $this->getPhobjectClassConstant('PANELKEY');
  }

  abstract public function getManagementPanelLabel();
  abstract public function getManagementPanelOrder();
  abstract public function buildManagementPanelContent();

  protected function buildManagementPanelActions() {
    return array();
  }

  final protected function newActions() {
    $actions = $this->buildManagementPanelActions();
    if (!$actions) {
      return null;
    }

    $viewer = $this->getViewer();

    $action_list = id(new PhabricatorActionListView())
      ->setViewer($viewer);

    foreach ($actions as $action) {
      $action_list->addAction($action);
    }

    return $action_list;
  }

  public function buildManagementPanelCurtain() {
    // TODO: Delete or fix this, curtains always render in the left gutter
    // at the moment.
    return null;

    $actions = $this->newActions();
    if (!$actions) {
      return null;
    }

    $viewer = $this->getViewer();

    $curtain = id(new PHUICurtainView())
      ->setViewer($viewer)
      ->setActionList($actions);

    return $curtain;
  }

  public static function getAllPanels() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getManagementPanelKey')
      ->setSortMethod('getManagementPanelOrder')
      ->execute();
  }

  final protected function newBox($header_text, $body) {
    return id(new PHUIObjectBoxView())
      ->setHeaderText($header_text)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($body);
  }

  final protected function newTimeline() {
    return $this->controller->newTimeline($this->getRepository());
  }

}
