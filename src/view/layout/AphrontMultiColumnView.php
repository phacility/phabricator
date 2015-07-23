<?php

final class AphrontMultiColumnView extends AphrontView {

  const GUTTER_SMALL = 'msr';
  const GUTTER_MEDIUM = 'mmr';
  const GUTTER_LARGE = 'mlr';

  private $id;
  private $columns = array();
  private $fluidLayout = false;
  private $fluidishLayout = false;
  private $gutter;
  private $border;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function getID() {
    return $this->id;
  }

  public function addColumn(
    $column,
    $class = null,
    $sigil = null,
    $metadata = null) {
    $this->columns[] = array(
      'column' => $column,
      'class' => $class,
      'sigil' => $sigil,
      'metadata' => $metadata,
    );
    return $this;
  }

  public function setFluidlayout($layout) {
    $this->fluidLayout = $layout;
    return $this;
  }

  public function setFluidishLayout($layout) {
    $this->fluidLayout = true;
    $this->fluidishLayout = $layout;
    return $this;
  }

  public function setGutter($gutter) {
    $this->gutter = $gutter;
    return $this;
  }

  public function setBorder($border) {
    $this->border = $border;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-multi-column-view-css');

    $classes = array();
    $classes[] = 'aphront-multi-column-inner';
    $classes[] = 'grouped';

    if ($this->fluidishLayout || $this->fluidLayout) {
      // we only support seven columns for now for fluid views; see T4054
      if (count($this->columns) > 7) {
        throw new Exception(pht('No more than 7 columns per view.'));
      }
    }

    $classes[] = 'aphront-multi-column-'.count($this->columns).'-up';

    $columns = array();
    $i = 0;
    foreach ($this->columns as $column_data) {
      $column_class = array('aphront-multi-column-column');
      if ($this->gutter) {
        $column_class[] = $this->gutter;
      }
      $outer_class = array('aphront-multi-column-column-outer');
      if (++$i === count($this->columns)) {
        $column_class[] = 'aphront-multi-column-column-last';
        $outer_class[] = 'aphront-multi-colum-column-outer-last';
      }
      $column = $column_data['column'];
      if ($column_data['class']) {
        $outer_class[] = $column_data['class'];
      }
      $column_sigil = idx($column_data, 'sigil');
      $column_metadata = idx($column_data, 'metadata');
      $column_inner = javelin_tag(
        'div',
        array(
          'class' => implode(' ', $column_class),
          'sigil' => $column_sigil,
          'meta'  => $column_metadata,
        ),
        $column);
      $columns[] = phutil_tag(
        'div',
        array(
          'class' => implode(' ', $outer_class),
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
      if ($this->fluidishLayout) {
        $classes[] = 'aphront-multi-column-fluidish';
      }
    } else {
      $classes[] = 'aphront-multi-column-fixed';
    }

    $board = phutil_tag(
      'div',
        array(
          'class' => implode(' ', $classes),
        ),
        $view);

    if ($this->border) {
      $board = id(new PHUIBoxView())
        ->setBorder(true)
        ->appendChild($board)
        ->addPadding(PHUI::PADDING_MEDIUM_TOP)
        ->addPadding(PHUI::PADDING_MEDIUM_BOTTOM);
    }

    return javelin_tag(
      'div',
        array(
          'class' => 'aphront-multi-column-view',
          'id' => $this->getID(),
          // TODO: It would be nice to convert this to an AphrontTagView and
          // use addSigil() from Workboards instead of hard-coding this.
          'sigil' => 'aphront-multi-column-view',
        ),
        $board);
  }
}
