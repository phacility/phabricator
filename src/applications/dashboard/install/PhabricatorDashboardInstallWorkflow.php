<?php

abstract class PhabricatorDashboardInstallWorkflow
  extends Phobject {

  private $request;
  private $viewer;
  private $dashboard;
  private $mode;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  final public function getDashboard() {
    return $this->dashboard;
  }

  final public function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  final public function getMode() {
    return $this->mode;
  }

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public function getWorkflowKey() {
    return $this->getPhobjectClassConstant('WORKFLOWKEY', 32);
  }

  final public static function getAllWorkflows() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getWorkflowKey')
      ->setSortMethod('getOrder')
      ->execute();
  }

  final public function getWorkflowMenuItem() {
    return $this->newWorkflowMenuItem();
  }

  abstract public function getOrder();
  abstract protected function newWorkflowMenuItem();

  final protected function newMenuItem() {
    return id(new PHUIObjectItemView())
      ->setClickable(true);
  }

  abstract public function handleRequest(AphrontRequest $request);

  final protected function newDialog() {
    $dashboard = $this->getDashboard();

    return id(new AphrontDialogView())
      ->setViewer($this->getViewer())
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton($dashboard->getURI());
  }

  final protected function newMenuFromItemMap(array $map) {
    $viewer = $this->getViewer();
    $dashboard = $this->getDashboard();

    $menu = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setFlush(true)
      ->setBig(true);

    foreach ($map as $key => $item) {
      $item->setHref(
        urisprintf(
          '/dashboard/install/%d/%s/%s/',
          $dashboard->getID(),
          $this->getWorkflowKey(),
          $key));

      $menu->addItem($item);
    }

    return $menu;
  }

  abstract protected function newProfileEngine();

  final protected function installDashboard($profile_object, $custom_phid) {
    $dashboard = $this->getDashboard();
    $engine = $this->newProfileEngine()
      ->setProfileObject($profile_object);

    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $config = PhabricatorProfileMenuItemConfiguration::initializeNewItem(
      $profile_object,
      new PhabricatorDashboardProfileMenuItem(),
      $custom_phid);

    $config->setMenuItemProperty('dashboardPHID', $dashboard->getPHID());

    $xactions = array();

    $editor = id(new PhabricatorProfileMenuEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($config, $xactions);

    $done_uri = $engine->getItemURI(urisprintf('view/%d/', $config->getID()));

    return id(new AphrontRedirectResponse())
      ->setURI($done_uri);
  }

  final protected function getDashboardDisplayName() {
    $dashboard = $this->getDashboard();
    return phutil_tag('strong', array(), $dashboard->getName());
  }

}
