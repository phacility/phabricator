<?php

final class AphrontMultiColumnView extends AphrontView {

  const GUTTER_SMALL = 'msr';
  const GUTTER_MEDIUM = 'mmr';
  const GUTTER_LARGE = 'mlr';

  private $column = array();
  private $fluidLayout = false;
  private $gutter;
  private $shadow;

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

  public function setShadow($shadow) {
    $this->shadow = $shadow;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-multi-column-view-css');

    $classes = array();
    $classes[] = 'aphront-multi-column-inner';
    $classes[] = 'grouped';

    if (count($this->columns) > 7) {
      throw new Exception("No more than 7 columns per view.");
    }

    $classes[] = 'aphront-multi-column-'.count($this->columns).'-up';

    $columns = array();
    $column_class = array();
    $column_class[] = 'aphront-multi-column-column';
    $outer_class = array();
    $outer_class[] = 'aphront-multi-column-column-outer';
    if ($this->gutter) {
      $column_class[] = $this->gutter;
    }
    $i = 0;
    foreach ($this->columns as $column) {
      if (++$i === count($this->columns)) {
        $column_class[] = 'aphront-multi-column-column-last';
        $outer_class[] = 'aphront-multi-colum-column-outer-last';
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
          'class' => implode(' ', $outer_class)
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

    if ($this->shadow) {
      $board = id(new PHUIBoxView())
        ->setShadow(true)
        ->appendChild($board)
        ->addPadding(PHUI::PADDING_MEDIUM_TOP)
        ->addPadding(PHUI::PADDING_MEDIUM_BOTTOM);
    }

    return phutil_tag(
      'div',
        array(
          'class' => 'aphront-multi-column-view'
        ),
        $board);
  }
}
