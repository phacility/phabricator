<?php

final class PHUIListView extends AphrontTagView {

  const NAVBAR_LIST = 'phui-list-navbar';
  const NAVBAR_VERTICAL = 'phui-list-navbar-vertical';
  const SIDENAV_LIST = 'phui-list-sidenav';
  const TABBAR_LIST = 'phui-list-tabbar';

  private $items = array();
  private $type;

  protected function canAppendChild() {
    return false;
  }

  public function newLabel($name, $key = null) {
    $item = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName($name);

    if ($key !== null) {
      $item->setKey($key);
    }

    $this->addMenuItem($item);

    return $item;
  }

  public function newLink($name, $href, $key = null) {
    $item = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setName($name)
      ->setHref($href);

    if ($key !== null) {
      $item->setKey($key);
    }

    $this->addMenuItem($item);

    return $item;
  }

  public function newButton($name, $href) {
    $item = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_BUTTON)
      ->setName($name)
      ->setHref($href);

    $this->addMenuItem($item);

    return $item;
  }

  public function addMenuItem(PHUIListItemView $item) {
    return $this->addMenuItemAfter(null, $item);
  }

  public function addMenuItemAfter($key, PHUIListItemView $item) {
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

  public function addMenuItemBefore($key, PHUIListItemView $item) {
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

  public function addMenuItemToLabel($key, PHUIListItemView $item) {
    $this->requireKey($key);

    $other = $this->getItem($key);
    if ($other->getType() != PHUIListItemView::TYPE_LABEL) {
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
        if ($other->getType() == PHUIListItemView::TYPE_LABEL) {
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

  public function willRender() {
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

  protected function getTagName() {
    return 'ul';
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-list-view-css');
    $classes = array();
    $classes[] = 'phui-list-view';
    if ($this->type) {
      switch ($this->type) {
        case self::NAVBAR_LIST:
          $classes[] = 'phui-list-navbar';
          $classes[] = 'phui-list-navbar-horizontal';
          break;
        case self::NAVBAR_VERTICAL:
          $classes[] = 'phui-list-navbar';
          $classes[] = 'phui-list-navbar-vertical';
          break;
        default:
          $classes[] = $this->type;
          break;
      }
    }

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    return $this->items;
  }
}
