<?php

final class AphrontMultiColumnView extends AphrontView {

  const GUTTER_SMALL = 'msr';
  const GUTTER_MEDIUM = 'mmr';
  const GUTTER_LARGE = 'mlr';

  private $column = array();
  private $fluidLayout = false;
  private $gutter;

  public function addColumn($column) {
    $this->columns[] = $column;
    return $this;
  }

  public function setFluidlayout($layout) {
    $this->fluidLayout = $layout;
    return $this;
  }

  public function setGutter($gutter) {
    $this->gutter = $gutter;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-multi-column-view-css');

    $classes = array();
    $classes[] = 'aphront-multi-column-inner';
    $classes[] = 'grouped';

    if (count($this->columns) > 6) {
      throw new Exception("No more than 6 columns per view.");
    }

    $classes[] = 'aphront-multi-column-'.count($this->columns).'-up';

    $columns = array();
    $column_class = array();
    $column_class[] = 'aphront-multi-column-column';
    if ($this->gutter) {
      $column_class[] = $this->gutter;
    }
    $i = 0;
    foreach ($this->columns as $column) {
      if (++$i === count($this->columns)) {
        $column_class[] = 'aphront-multi-column-column-last';
      }
      $column_inner = phutil_tag(
        'div',
          array(
          'class' => implode(' ', $column_class)
          ),
        $column);
      $columns[] = phutil_tag(
        'div',
          array(
          'class' => 'aphront-multi-column-column-outer'
          ),
        $column_inner);
    }

    $view = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $columns,
      ));

    $classes = array();
    $classes[] = 'aphront-multi-column-outer';
    if ($this->fluidLayout) {
      $classes[] = 'aphront-multi-column-fluid';
    } else {
      $classes[] = 'aphront-multi-column-fixed';
    }

    $board = phutil_tag(
      'div',
        array(
          'class' => implode(' ', $classes)
        ),
        $view);

    return phutil_tag(
      'div',
        array(
          'class' => 'aphront-multi-column-view'
        ),
        $board);
  }
}
