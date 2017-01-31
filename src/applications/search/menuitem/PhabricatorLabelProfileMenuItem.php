<?php

final class PhabricatorLabelProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'label';
  const FIELD_NAME = 'name';

  public function getMenuItemTypeIcon() {
    return 'fa-map-signs';
  }

  public function getMenuItemTypeName() {
    return pht('Label');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->getLabelName($config);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey(self::FIELD_NAME)
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setValue($this->getLabelName($config)),
    );
  }

  private function getLabelName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('name');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $name = $this->getLabelName($config);

    $item = $this->newItem()
      ->setName($name)
      ->setType(PHUIListItemView::TYPE_LABEL);

    return array(
      $item,
    );
  }

  public function validateTransactions(
    PhabricatorProfileMenuItemConfiguration $config,
    $field_key,
    $value,
    array $xactions) {

    $viewer = $this->getViewer();
    $errors = array();

    if ($field_key == self::FIELD_NAME) {
      if ($this->isEmptyTransaction($value, $xactions)) {
       $errors[] = $this->newRequiredError(
         pht('You must choose a label name.'),
         $field_key);
      }
    }

    return $errors;
  }
}
