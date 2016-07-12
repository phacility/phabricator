<?php

final class PhabricatorLinkProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'link';

  public function getPanelTypeIcon() {
    return 'fa-link';
  }

  public function getPanelTypeName() {
    return pht('Link');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {
    return $this->getLinkName($config);
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setValue($this->getLinkName($config)),
      id(new PhabricatorTextEditField())
        ->setKey('uri')
        ->setLabel(pht('URI'))
        ->setIsRequired(true)
        ->setValue($this->getLinkURI($config)),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setIconSet(new PhabricatorProfilePanelIconSet())
        ->setValue($this->getLinkIcon($config)),
    );
  }

  private function getLinkName(
    PhabricatorProfilePanelConfiguration $config) {
    return $config->getPanelProperty('name');
  }

  private function getLinkIcon(
    PhabricatorProfilePanelConfiguration $config) {
    return $config->getPanelProperty('icon', 'link');
  }

  private function getLinkURI(
    PhabricatorProfilePanelConfiguration $config) {
    return $config->getPanelProperty('uri');
  }

  private function isValidLinkURI($uri) {
    return PhabricatorEnv::isValidURIForLink($uri);
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $icon = $this->getLinkIcon($config);
    $name = $this->getLinkName($config);
    $href = $this->getLinkURI($config);

    if (!$this->isValidLinkURI($href)) {
      $href = '#';
    }

    $icon_object = id(new PhabricatorProfilePanelIconSet())
      ->getIcon($icon);
    if ($icon_object) {
      $icon_class = $icon_object->getIcon();
    } else {
      $icon_class = 'fa-link';
    }

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon_class);

    return array(
      $item,
    );
  }

}
