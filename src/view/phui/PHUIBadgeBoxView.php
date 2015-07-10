<?php

final class PHUIBadgeBoxView extends AphrontTagView {

  private $items = array();

  public function addItem($item) {
    $this->items[] = $item;
    return $this;
  }

  public function addItems($items) {
    foreach ($items as $item) {
      $this->items[] = $item;
    }
    return $this;
  }

  protected function getTagName() {
    return 'ul';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-badge-view-css');

    $classes = array();
    $classes[] = 'phui-badge-flex-view';
    $classes[] = 'grouped';

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    $items = array();
    foreach ($this->items as $item) {
      $items[] = phutil_tag(
        'li',
        array(
          'class' => 'phui-badge-flex-item',
        ),
        $item);
    }
    return $items;

  }
}
