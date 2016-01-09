<?php

final class PHUIMainMenuView extends AphrontView {

  private $menuItem;
  private $extraContent = array();
  private $order;

  public function setMenuBarItem(PHUIListItemView $menu_item) {
    $this->menuItem = $menu_item;
    return $this;
  }

  public function getMenuBarItem() {
    return $this->menuItem;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function getOrder() {
    return $this->order;
  }

  public function render() {
    return $this->renderChildren();
  }

}
