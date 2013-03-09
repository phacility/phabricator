<?php

final class PhabricatorMenuView extends AphrontTagView {

  private $items = array();

  protected function canAppendChild() {
    return false;
  }

  public function newLabel($name, $key = null) {
    $item = id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_LABEL)
      ->setName($name);

    if ($key !== null) {
      $item->setKey($key);
    }

    $this->addMenuItem($item);

    return $item;
  }

  public function newLink($name, $href, $key = null) {
    $item = id(new PhabricatorMenuItemView())
      ->setType(PhabricatorMenuItemView::TYPE_LINK)
      ->setName($name)
      ->setHref($href);

    if ($key !== null) {
      $item->setKey($key);
    }

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
    return $this->addMenuItemAfter(null, $item);
  }

  public function addMenuItemAfter($key, PhabricatorMenuItemView $item) {
    if ($key === null) {
      $this->items[] = $item;
      return $this;
    }

    if (!$this->getItem($key)) {
      throw new Exception(pht("No such key '%s' to add menu item after!",
        $key));
    }

    $result = array();
    foreach ($this->items as $other) {
      $result[] = $other;
      if ($other->getKey() == $key) {
        $result[] = $item;
      }
    }

    $this->items = $result;
    return $this;
  }

  public function addMenuItemBefore($key, PhabricatorMenuItemView $item) {
    if ($key === null) {
      array_unshift($this->items, $item);
      return $this;
    }

    $this->requireKey($key);

    $result = array();
    foreach ($this->items as $other) {
      if ($other->getKey() == $key) {
        $result[] = $item;
      }
      $result[] = $other;
    }

    $this->items = $result;
    return $this;
  }

  public function addMenuItemToLabel($key, PhabricatorMenuItemView $item) {
    $this->requireKey($key);

    $other = $this->getItem($key);
    if ($other->getType() != PhabricatorMenuItemView::TYPE_LABEL) {
      throw new Exception(pht("Menu item '%s' is not a label!", $key));
    }

    $seen = false;
    $after = null;
    foreach ($this->items as $other) {
      if (!$seen) {
        if ($other->getKey() == $key) {
          $seen = true;
        }
      } else {
        if ($other->getType() == PhabricatorMenuItemView::TYPE_LABEL) {
          break;
        }
      }
      $after = $other->getKey();
    }

    return $this->addMenuItemAfter($after, $item);
  }

  private function requireKey($key) {
    if (!$this->getItem($key)) {
      throw new Exception(pht("No menu item with key '%s' exists!", $key));
    }
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
            pht("Menu contains duplicate items with key '%s'!", $key));
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

  protected function getTagContent() {
    return $this->items;
  }
}
