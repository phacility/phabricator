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
  abstract public function buildManagementPanelCurtain();

  public function getManagementPanelIcon() {
    return 'fa-pencil';
  }

  protected function buildManagementPanelActions() {
    return array();
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return true;
  }

  public function getNewActionList() {
    $viewer = $this->getViewer();
    $action_id = celerity_generate_unique_node_id();

    return id(new PhabricatorActionListView())
      ->setViewer($viewer)
      ->setID($action_id);
  }

  public function getNewCurtainView(PhabricatorActionListView $action_list) {
    $viewer = $this->getViewer();
    return id(new PHUICurtainView())
      ->setViewer($viewer)
      ->setActionList($action_list);
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

  final public function getPanelURI() {
    $repository = $this->getRepository();
    $key = $this->getManagementPanelKey();
    return $repository->getPathURI("manage/{$key}/");
  }

  final public function newEditEnginePage() {
    $field_keys = $this->getEditEngineFieldKeys();
    if (!$field_keys) {
      return null;
    }

    $key = $this->getManagementPanelKey();
    $label = $this->getManagementPanelLabel();
    $panel_uri = $this->getPanelURI();

    return id(new PhabricatorEditPage())
      ->setKey($key)
      ->setLabel($label)
      ->setViewURI($panel_uri)
      ->setFieldKeys($field_keys);
  }

  protected function getEditEngineFieldKeys() {
    return array();
  }

  protected function getEditPageURI($page = null) {
    if ($page === null) {
      $page = $this->getManagementPanelKey();
    }

    $repository = $this->getRepository();
    $id = $repository->getID();
    return "/diffusion/edit/{$id}/page/{$page}/";
  }

  public function getPanelNavigationURI() {
    return $this->getPanelURI();
  }

}
