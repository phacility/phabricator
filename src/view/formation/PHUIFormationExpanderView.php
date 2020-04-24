<?php

final class PHUIFormationExpanderView
  extends AphrontAutoIDView {

  private $tooltip;
  private $columnItem;

  public function setTooltip($tooltip) {
    $this->tooltip = $tooltip;
    return $this;
  }

  public function getTooltip() {
    return $this->tooltip;
  }

  public function setColumnItem($column_item) {
    $this->columnItem = $column_item;
    return $this;
  }

  public function getColumnItem() {
    return $this->columnItem;
  }

  public function render() {
    $classes = array();
    $classes[] = 'phui-formation-view-expander';

    $is_right = $this->getColumnItem()->getIsRightAligned();
    if ($is_right) {
      $icon = id(new PHUIIconView())
        ->setIcon('fa-chevron-left grey');
      $classes[] = 'phui-formation-view-expander-right';
    } else {
      $icon = id(new PHUIIconView())
        ->setIcon('fa-chevron-right grey');
      $classes[] = 'phui-formation-view-expander-left';
    }

    $icon_view = phutil_tag(
      'div',
      array(
        'class' => 'phui-formation-view-expander-icon',
      ),
      $icon);

    return javelin_tag(
      'div',
      array(
        'id' => $this->getID(),
        'class' => implode(' ', $classes),
        'sigil' => 'has-tooltip',
        'style' => 'display: none',
        'meta' => array(
          'tip' => $this->getTooltip(),
          'align' => 'E',
        ),
      ),
      $icon_view);
  }

}
