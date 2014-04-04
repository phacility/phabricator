<?php

final class PHUIPropertyGroupView extends AphrontTagView {

  private $items;

  public function addPropertyList(PHUIPropertyListView $item) {
    $this->items[] = $item;
  }

  protected function canAppendChild() {
    return false;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-property-list-view',
    );
  }

  protected function getTagContent() {
    return $this->items;
  }
}
