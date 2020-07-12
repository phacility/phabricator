<?php

final class FuelGridRowView
  extends FuelView {

  private $cells = array();

  public function newCell() {
    $cell = new FuelGridCellView();
    $this->cells[] = $cell;
    return $cell;
  }

  public function render() {
    $cells = $this->cells;

    $classes = array();
    $classes[] = 'fuel-grid-row';

    $classes[] = sprintf(
      'fuel-grid-cell-count-%d',
      count($cells));

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $cells);
  }

}
