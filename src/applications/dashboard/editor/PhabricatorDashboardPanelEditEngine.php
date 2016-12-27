<?php

final class PhabricatorDashboardPanelEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'dashboard.panel';

  private $panelType;

  public function setPanelType($panel_type) {
    $this->panelType = $panel_type;
    return $this;
  }

  public function getPanelType() {
    return $this->panelType;
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

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the panel.'))
        ->setConduitDescription(pht('Rename the panel.'))
        ->setConduitTypeDescription(pht('New panel name.'))
        ->setTransactionType(PhabricatorDashboardPanelTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );
  }

}
