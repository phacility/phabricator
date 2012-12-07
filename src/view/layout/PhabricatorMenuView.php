<?php

final class PhabricatorMenuView extends AphrontView {

  private $items = array();
  private $map = array();
  private $classes = array();

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
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

  public function addMenuItem(PhabricatorMenuItemView $item) {
    $key = $item->getKey();
    if ($key !== null) {
      if (isset($this->map[$key])) {
        throw new Exception(
          "Menu contains duplicate items with key '{$key}'!");
      }
      $this->map[$key] = $item;
    }

    $this->items[] = $item;
    $this->appendChild($item);

    return $this;
  }

  public function getItem($key) {
    return idx($this->map, $key);
  }

  public function getItems() {
    return $this->items;
  }

  public function render() {
    $classes = $this->classes;
    $classes[] = 'phabricator-menu-view';

    return phutil_render_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->renderChildren());
  }

}
