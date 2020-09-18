<?php

final class FuelGridView
  extends FuelComponentView {

  private $rows = array();

  public function newRow() {
    $row = new FuelGridRowView();
    $this->rows[] = $row;
    return $row;
  }

  public function render() {
    require_celerity_resource('fuel-grid-css');

    $rows = $this->rows;

    $body = phutil_tag(
      'div',
      array(
        'class' => 'fuel-grid-body',
      ),
      $rows);

    $grid = phutil_tag(
      'div',
      array(
        'class' => 'fuel-grid',
      ),
      $body);

    return $this->newComponentTag(
      'div',
      array(
        'class' => 'fuel-grid-component',
      ),
      $grid);
  }

}
