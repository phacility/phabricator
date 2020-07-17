<?php

final class FuelMenuView
  extends FuelComponentView {

  private $items = array();

  public function newItem() {
    $item = new FuelMenuItemView();
    $this->items[] = $item;
    return $item;
  }

  public function render() {
    require_celerity_resource('fuel-menu-css');

    $items = $this->items;

    if (!$items) {
      return null;
    }

    $list = phutil_tag(
      'div',
      array(
        'class' => 'fuel-menu',
      ),
      $items);

    return $this->newComponentTag(
      'div',
      array(
        'class' => 'fuel-menu-component',
      ),
      $list);
  }

}
