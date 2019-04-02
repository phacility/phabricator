<?php

final class PhabricatorDashboardPanelEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'dashboard.panel';

  private $panelType;
  private $dashboard;
  private $columnID;

  public function setPanelType($panel_type) {
    $this->panelType = $panel_type;
    return $this;
  }

  public function getPanelType() {
    return $this->panelType;
  }

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function getDashboard() {
    return $this->dashboard;
  }

  public function setColumnID($column_id) {
    $this->columnID = $column_id;
    return $this;
  }

  public function getColumnID() {
    return $this->columnID;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Dashboard Panels');
  }

  public function getSummaryHeader() {
    return pht('Edit Dashboard Panels');
  }

  protected function supportsSearch() {
    return true;
  }

  public function getSummaryText() {
    return pht('This engine is used to modify dashboard panels.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    $panel = PhabricatorDashboardPanel::initializeNewPanel($viewer);

    if ($this->panelType) {
      $panel->setPanelType($this->panelType);
    }

    return $panel;
  }

  protected function newObjectQuery() {
    return new PhabricatorDashboardPanelQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Dashboard Panel');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Panel');
  }

  protected function getObjectCreateCancelURI($object) {
    $dashboard = $this->getDashboard();
    if ($dashboard) {
      return $dashboard->getURI();
    }

    return parent::getObjectCreateCancelURI($object);
  }

  public function getEffectiveObjectEditDoneURI($object) {
    $dashboard = $this->getDashboard();
    if ($dashboard) {
      return $dashboard->getURI();
    }

    return parent::getEffectiveObjectEditDoneURI($object);
  }

  protected function getObjectEditCancelURI($object) {
    $dashboard = $this->getDashboard();
    if ($dashboard) {
      return $dashboard->getURI();
    }

    return parent::getObjectEditCancelURI($object);
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Panel: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Panel');
  }

  protected function getObjectCreateShortText() {
    return pht('Edit Panel');
  }

  protected function getObjectName() {
    return pht('Dashboard Panel');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function didApplyTransactions($object, array $xactions) {
    $dashboard = $this->getDashboard();
    if ($dashboard) {
      $viewer = $this->getViewer();
      $controller = $this->getController();
      $request = $controller->getRequest();

      PhabricatorDashboardTransactionEditor::addPanelToDashboard(
        $viewer,
        PhabricatorContentSource::newFromRequest($request),
        $object,
        $dashboard,
        (int)$this->getColumnID());
    }
  }

  protected function buildCustomEditFields($object) {
    $fields = array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the panel.'))
        ->setConduitDescription(pht('Rename the panel.'))
        ->setConduitTypeDescription(pht('New panel name.'))
        ->setTransactionType(
          PhabricatorDashboardPanelNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );

    $panel_fields = $object->getEditEngineFields();
    foreach ($panel_fields as $panel_field) {
      $fields[] = $panel_field;
    }

    return $fields;
  }

}
