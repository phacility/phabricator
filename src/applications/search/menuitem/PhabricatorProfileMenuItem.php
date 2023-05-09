<?php

abstract class PhabricatorProfileMenuItem extends Phobject {

  private $viewer;
  private $engine;

  public function getMenuItemTypeIcon() {
    return null;
  }

  abstract public function getMenuItemTypeName();

  abstract public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config);

  protected function getNameFromConfig(
    PhabricatorProfileMenuItemConfiguration $config,
    $default = '') {
    $name = $config->getMenuItemProperty('name');

    if ($name !== null && strlen($name)) {
      return $name;
    }
    return $default;
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array();
  }

  public function canAddToObject($object) {
    return false;
  }

  public function shouldEnableForObject($object) {
    return true;
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setEngine(PhabricatorProfileMenuEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  final public function getMenuItemKey() {
    return $this->getPhobjectClassConstant('MENUITEMKEY');
  }

  final public static function getAllMenuItems() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getMenuItemKey')
      ->execute();
  }

  final protected function newItemView() {
    return new PhabricatorProfileMenuItemView();
  }

  public function willGetMenuItemViewList(array $items) {}

  final public function getMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {
    $list = $this->newMenuItemViewList($config);

    if (!is_array($list)) {
      throw new Exception(
        pht(
          'Expected "newMenuItemViewList()" to return a list (in class "%s"), '.
          'but it returned something else ("%s").',
          get_class($this),
          phutil_describe_type($list)));
    }

    assert_instances_of($list, 'PhabricatorProfileMenuItemView');

    foreach ($list as $view) {
      $view->setMenuItemConfiguration($config);
    }

    return $list;
  }

  abstract protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config);


  public function newPageContent(
    PhabricatorProfileMenuItemConfiguration $config) {
    return null;
  }

  public function getItemViewURI(
    PhabricatorProfileMenuItemConfiguration $config) {

    $engine = $this->getEngine();
    $key = $config->getItemIdentifier();

    return $engine->getItemURI("view/{$key}/");
  }

  public function validateTransactions(
    PhabricatorProfileMenuItemConfiguration $config,
    $field_key,
    $value,
    array $xactions) {
    return array();
  }

  final protected function isEmptyTransaction($value, array $xactions) {
    $result = $value;
    foreach ($xactions as $xaction) {
      $result = $xaction['new'];
    }

    return !strlen($result);
  }

  final protected function newError($title, $message, $xaction = null) {
    return new PhabricatorApplicationTransactionValidationError(
      PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY,
      $title,
      $message,
      $xaction);
  }

  final protected function newRequiredError($message, $type) {
    $xaction = id(new PhabricatorProfileMenuItemConfigurationTransaction())
      ->setMetadataValue('property.key', $type);

    return $this->newError(pht('Required'), $message, $xaction)
      ->setIsMissingFieldError(true);
  }

  final protected function newInvalidError($message, $xaction = null) {
    return $this->newError(pht('Invalid'), $message, $xaction);
  }

  final protected function newEmptyView($title, $message) {
    return id(new PHUIInfoView())
      ->setTitle($title)
      ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
      ->setErrors(
        array(
          $message,
        ));
  }

  public function getAffectedObjectPHIDs(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array();
  }

}
