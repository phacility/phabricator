<?php

final class PhabricatorDividerProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'divider';

  public function getPanelTypeIcon() {
    return 'fa-minus';
  }

  public function getPanelTypeName() {
    return pht('Divider');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {
    return pht("\xE2\x80\x94");
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'This is a visual divider which you can use to separate '.
            'sections in the menu. It does not have any configurable '.
            'options.')),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $item = $this->newItem()
      ->addClass('phui-divider');

    return array(
      $item,
    );
  }

}
