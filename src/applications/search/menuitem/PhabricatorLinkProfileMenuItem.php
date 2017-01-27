<?php

final class PhabricatorLinkProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'link';

  const FIELD_URI = 'uri';
  const FIELD_NAME = 'name';

  public function getMenuItemTypeIcon() {
    return 'fa-link';
  }

  public function getMenuItemTypeName() {
    return pht('Link');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->getLinkName($config);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey(self::FIELD_NAME)
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setValue($this->getLinkName($config)),
      id(new PhabricatorTextEditField())
        ->setKey(self::FIELD_URI)
        ->setLabel(pht('URI'))
        ->setIsRequired(true)
        ->setValue($this->getLinkURI($config)),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setIconSet(new PhabricatorProfileMenuItemIconSet())
        ->setValue($this->getLinkIcon($config)),
    );
  }

  private function getLinkName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('name');
  }

  private function getLinkIcon(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('icon', 'link');
  }

  private function getLinkURI(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('uri');
  }

  private function isValidLinkURI($uri) {
    return PhabricatorEnv::isValidURIForLink($uri);
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $icon = $this->getLinkIcon($config);
    $name = $this->getLinkName($config);
    $href = $this->getLinkURI($config);

    if (!$this->isValidLinkURI($href)) {
      $href = '#';
    }

    $icon_object = id(new PhabricatorProfileMenuItemIconSet())
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
         pht('You must choose a link name.'),
         $field_key);
      }
    }

    if ($field_key == self::FIELD_URI) {
      if ($this->isEmptyTransaction($value, $xactions)) {
       $errors[] = $this->newRequiredError(
         pht('You must choose a URI to link to.'),
         $field_key);
      }

      foreach ($xactions as $xaction) {
        $new = $xaction['new'];

        if (!$new) {
          continue;
        }

        if ($new === $value) {
          continue;
        }

        if (!$this->isValidLinkURI($new)) {
          $errors[] = $this->newInvalidError(
            pht(
              'URI "%s" is not a valid link URI. It should be a full, valid '.
              'URI beginning with a protocol like "%s".',
              $new,
              'https://'),
            $xaction['xaction']);
        }
      }
    }

    return $errors;
  }
}
