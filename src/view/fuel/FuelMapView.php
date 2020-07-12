<?php

final class FuelMapView
  extends FuelComponentView {

  private $items = array();

  public function newItem() {
    $item = new FuelMapItemView();
    $this->items[] = $item;
    return $item;
  }

  public function render() {
    require_celerity_resource('fuel-map-css');

    $items = $this->items;

    if (!$items) {
      return null;
    }

    $body = phutil_tag(
      'div',
      array(
        'class' => 'fuel-map-items',
      ),
      $items);

    $map = phutil_tag(
      'div',
      array(
        'class' => 'fuel-map',
      ),
      $body);

    return $this->newComponentTag(
      'div',
      array(
        'class' => 'fuel-map-component',
      ),
      $map);
  }

}
