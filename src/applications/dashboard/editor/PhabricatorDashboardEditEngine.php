<?php

final class PhabricatorDashboardEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'dashboard';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Dashboards');
  }

  public function getSummaryHeader() {
    return pht('Edit Dashboards');
  }

  public function getSummaryText() {
    return pht('This engine is used to modify dashboards.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhabricatorDashboard::initializeNewDashboard($viewer);
  }

  protected function newObjectQuery() {
    return new PhabricatorDashboardQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Dashboard');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Dashboard');
  }

  protected function getObjectCreateCancelURI($object) {
    return '/dashboard/';
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Dashboard: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Dashboard');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Dashboard');
  }

  protected function getObjectName() {
    return pht('Dashboard');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    $layout_options = PhabricatorDashboardLayoutMode::getLayoutModeMap();

    $fields = array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the dashboard.'))
        ->setConduitDescription(pht('Rename the dashboard.'))
        ->setConduitTypeDescription(pht('New dashboard name.'))
        ->setTransactionType(
          PhabricatorDashboardNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setTransactionType(
            PhabricatorDashboardIconTransaction::TRANSACTIONTYPE)
        ->setIconSet(new PhabricatorDashboardIconSet())
        ->setDescription(pht('Dashboard icon.'))
        ->setConduitDescription(pht('Change the dashboard icon.'))
        ->setConduitTypeDescription(pht('New dashboard icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('layout')
        ->setLabel(pht('Layout'))
        ->setDescription(pht('Dashboard layout mode.'))
        ->setConduitDescription(pht('Change the dashboard layout mode.'))
        ->setConduitTypeDescription(pht('New dashboard layout mode.'))
        ->setTransactionType(
          PhabricatorDashboardLayoutTransaction::TRANSACTIONTYPE)
        ->setOptions($layout_options)
        ->setValue($object->getRawLayoutMode()),
    );

    return $fields;
  }

}
