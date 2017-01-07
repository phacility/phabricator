<?php

final class PhabricatorEditEngineProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'editengine';

  private $form;

  public function getMenuItemTypeIcon() {
    return 'fa-plus';
  }

  public function getMenuItemTypeName() {
    return pht('Forms');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function attachForm($form) {
    $this->form = $form;
    return $this;
  }

  public function getForm() {
    $form = $this->form;
    if (!$form) {
      return null;
    }
    return $form;
  }

  public function willBuildNavigationItems(array $items) {
    $viewer = $this->getViewer();
    $engines = PhabricatorEditEngine::getAllEditEngines();
    $engine_keys = array_keys($engines);
    $forms = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys($engine_keys)
      ->withIsDisabled(false)
      ->execute();
    $form_engines = mgroup($forms, 'getEngineKey');
    $form_ids = $forms;

    $builtin_map = array();
    foreach ($form_engines as $engine_key => $form_engine) {
      $builtin_map[$engine_key] = mpull($form_engine, null, 'getBuiltinKey');
    }

    foreach ($items as $item) {
      $key = $item->getMenuItemProperty('formKey');
      list($engine_key, $form_key) = explode('/', $key);
      if (is_numeric($form_key)) {
        $form = idx($form_ids, $form_key, null);
        $item->getMenuItem()->attachForm($form);
      } else if (isset($builtin_map[$engine_key][$form_key])) {
        $form = $builtin_map[$engine_key][$form_key];
        $item->getMenuItem()->attachForm($form);
      }
    }
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $form = $this->getForm();
    if (!$form) {
      return pht('(Restricted/Invalid Form)');
    }
    if (strlen($this->getName($config))) {
      return $this->getName($config);
    } else {
      return $form->getName();
    }
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getName($config)),
      id(new PhabricatorDatasourceEditField())
        ->setKey('formKey')
        ->setLabel(pht('Form'))
        ->setDatasource(new PhabricatorEditEngineDatasource())
        ->setSingleValue($config->getMenuItemProperty('formKey')),
    );
  }

  private function getName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('name');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $form = $this->getForm();
    if (!$form) {
      return array();
    }
    $engine = $form->getEngine();
    $form_key = $form->getIdentifier();

    $icon = $form->getIcon();
    $name = $this->getDisplayName($config);
    $href = $engine->getEditURI(null, "form/{$form_key}/");

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
