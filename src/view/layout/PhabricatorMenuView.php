<?php

final class PhabricatorMenuView extends AphrontTagView {

  private $items = array();

  public function newLabel($name) {
    $item = id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_LABEL)
      ->setName($name);

    $this->addMenuItem($item);

    return $item;
  }

  public function newLink($name, $href) {
    $item = id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_LINK)
      ->setName($name)
      ->setHref($href);

    $this->addMenuItem($item);

    return $item;
  }

  public function newButton($name, $href) {
    $item = id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_BUTTON)
      ->setName($name)
      ->setHref($href);

    $this->addMenuItem($item);

    return $item;
  }

  public function addMenuItem(PhabricatorMenuItemView $item) {
    $key = $item->getKey();
    $this->items[] = $item;
    $this->appendChild($item);

    return $this;
  }

  public function getItem($key) {
    $key = (string)$key;

    // NOTE: We could optimize this, but need to update any map when items have
    // their keys change. Since that's moderately complex, wait for a profile
    // or use case.

    foreach ($this->items as $item) {
      if ($item->getKey() == $key) {
        return $item;
      }
    }

    return null;
  }

  public function getItems() {
    return $this->items;
  }

  protected function willRender() {
    $key_map = array();
    foreach ($this->items as $item) {
      $key = $item->getKey();
      if ($key !== null) {
        if (isset($key_map[$key])) {
          throw new Exception(
            "Menu contains duplicate items with key '{$key}'!");
        }
        $key_map[$key] = $item;
      }
    }
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phabricator-menu-view',
    );
  }
}
