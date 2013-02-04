<?php

final class PhabricatorMenuView extends AphrontTagView {

  private $items = array();

  protected function canAppendChild() {
    return false;
  }

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
    return $this->addMenuItemAfter(null, $item);
  }

  public function addMenuItemAfter($key, PhabricatorMenuItemView $item) {
    if ($key === null) {
      $this->items[] = $item;
      return $this;
    }

    if (!$this->getItem($key)) {
      throw new Exception("No such key '{$key}' to add menu item after!");
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
      throw new Exception("Menu item '{$key}' is not a label!");
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
      throw new Exception("No menu item with key '{$key}' exists!");
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

  protected function getTagContent() {
    return $this->renderSingleView($this->items);
  }
}
