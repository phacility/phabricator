<?php

final class PhabricatorDividerProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'divider';

  public function getMenuItemTypeIcon() {
    return 'fa-minus';
  }

  public function getMenuItemTypeName() {
    return pht('Divider');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return pht("\xE2\x80\x94");
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'This is a visual divider which you can use to separate '.
            'sections in the menu. It does not have any configurable '.
            'options.')),
    );
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $item = $this->newItemView()
      ->setIsDivider(true);

    return array(
      $item,
    );
  }

}
