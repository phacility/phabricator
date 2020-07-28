<?php

final class FuelHandleListView
  extends FuelComponentView {

  private $items = array();

  public function addHandleList(PhabricatorHandleList $list) {
    $this->items[] = array(
      'type' => 'list',
      'item' => $list,
    );
    return $this;
  }

  public function render() {
    require_celerity_resource('fuel-handle-list-css');

    $items = $this->items;

    $item_views = array();
    foreach ($items as $item) {
      $item_type = $item['type'];
      $item_item = $item['item'];

      switch ($item_type) {
        case 'list':
          foreach ($item_item as $handle) {
            $item_views[] = id(new FuelHandleListItemView())
              ->setHandle($handle);
          }
          break;
      }
    }

    $body = phutil_tag(
      'div',
      array(
        'class' => 'fuel-handle-list-body',
      ),
      $item_views);

    $list = phutil_tag(
      'div',
      array(
        'class' => 'fuel-handle-list',
      ),
      $body);

    return $this->newComponentTag(
      'div',
      array(
        'class' => 'fuel-handle-list-component',
      ),
      $list);
  }

}
