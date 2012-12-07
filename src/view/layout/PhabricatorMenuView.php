<?php

final class PhabricatorMenuView extends AphrontView {

  private $items = array();
  private $map = array();

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
    require_celerity_resource('phabricator-menu-view-css');
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-menu-view',
      ),
      $this->renderChildren());
  }

}
