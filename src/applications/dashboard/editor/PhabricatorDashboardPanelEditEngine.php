<?php

final class PhabricatorDashboardPanelEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'dashboard.panel';

  private $panelType;
  private $contextObject;
  private $columnKey;

  public function setPanelType($panel_type) {
    $this->panelType = $panel_type;
    return $this;
  }

  public function getPanelType() {
    return $this->panelType;
  }

  public function setContextObject($context) {
    $this->contextObject = $context;
    return $this;
  }

  public function getContextObject() {
    return $this->contextObject;
  }

  public function setColumnKey($column_key) {
    $this->columnKey = $column_key;
    return $this;
  }

  public function getColumnKey() {
    return $this->columnKey;
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

  protected function newEditableObjectForDocumentation() {
    $panel = parent::newEditableObjectForDocumentation();

    $text_type = id(new PhabricatorDashboardTextPanelType())
      ->getPanelTypeKey();

    $panel->setPanelType($text_type);

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
    $context = $this->getContextObject();
    if ($context) {
      return $context->getURI();
    }

    return parent::getObjectCreateCancelURI($object);
  }

  public function getEffectiveObjectEditDoneURI($object) {
    $context = $this->getContextObject();
    if ($context) {
      return $context->getURI();
    }

    return parent::getEffectiveObjectEditDoneURI($object);
  }

  protected function getObjectEditCancelURI($object) {
    $context = $this->getContextObject();
    if ($context) {
      return $context->getURI();
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
    $context = $this->getContextObject();

    if ($context instanceof PhabricatorDashboard) {
      // Only add the panel to the dashboard when we're creating a new panel,
      // not if we're editing an existing panel.
      if (!$this->getIsCreate()) {
        return;
      }

      $viewer = $this->getViewer();
      $controller = $this->getController();
      $request = $controller->getRequest();

      $dashboard = $context;

      $xactions = array();

      $ref_list = clone $dashboard->getPanelRefList();

      $ref_list->newPanelRef($object, $this->getColumnKey());
      $new_panels = $ref_list->toDictionary();

      $xactions[] = $dashboard->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhabricatorDashboardPanelsTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_panels);

      $editor = $dashboard->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($dashboard, $xactions);
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
