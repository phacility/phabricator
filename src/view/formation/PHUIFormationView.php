<?php

final class PHUIFormationView
  extends AphrontView {

  private $items = array();

  public function newFlankColumn() {
    $item = $this->newItem(new PHUIFormationFlankView());
    return $item->getColumn();
  }

  public function newContentColumn() {
    $item = $this->newItem(new PHUIFormationContentView());
    return $item->getColumn();
  }

  private function newItem(PHUIFormationColumnView $column) {
    $item = id(new PHUIFormationColumnItem())
      ->setColumn($column);

    $column->setColumnItem($item);

    $this->items[] = $item;

    return $item;
  }

  public function render() {
    require_celerity_resource('phui-formation-view-css');

    $items = $this->items;

    $items = $this->generateControlBindings($items);
    $items = $this->generateExpanders($items);
    $items = $this->generateResizers($items);

    $cells = array();
    foreach ($items as $item) {
      $style = array();

      $column = $item->getColumn();

      $width = $column->getWidth();
      if ($width !== null) {
        $style[] = sprintf('width: %dpx;', $width);
      }

      if (!$column->getIsVisible()) {
        $style[] = 'display: none;';
      }

      $cells[] = phutil_tag(
        'td',
        array(
          'id' => $item->getID(),
          'style' => implode(' ', $style),
        ),
        array(
          $column,
          $item->getExpanders(),
        ));
    }

    $formation_id = celerity_generate_unique_node_id();

    $table_row = phutil_tag('tr', array(), $cells);
    $table_body = phutil_tag('tbody', array(), $table_row);
    $table = phutil_tag(
      'table',
      array(
        'class' => 'phui-formation-view',
        'id' => $formation_id,
      ),
      $table_body);

    $phuix_columns = array();
    foreach ($items as $item) {
      $phuix_columns[] = $item->newClientProperties();
    }

    Javelin::initBehavior(
      'phuix-formation-view',
      array(
        'nodeID' => $formation_id,
        'columns' => $phuix_columns,
      ));

    return $table;
  }

  private function newColumnExpanderView() {
    return new PHUIFormationExpanderView();
  }

  private function newResizerItem() {
    return $this->newItem(new PHUIFormationResizerView());
  }

  private function generateControlBindings(array $items) {
    $count = count($items);

    if (!$count) {
      return $items;
    }

    $last_control = null;

    for ($ii = 0; $ii < $count; $ii++) {
      $item = $items[$ii];
      $column = $item->getColumn();

      $is_control = $column->getIsControlColumn();
      if ($is_control) {
        $last_control = $ii;
      }
    }

    if ($last_control === null) {
      return $items;
    }

    for ($ii = ($count - 1); $ii >= 0; $ii--) {
      $item = $items[$ii];
      $column = $item->getColumn();

      $is_control = $column->getIsControlColumn();
      if ($is_control) {
        $last_control = $ii;
        continue;
      }

      $is_right = ($last_control < $ii);

      $item
        ->setControlItem($items[$last_control])
        ->setIsRightAligned($is_right);
    }

    return $items;
  }

  private function generateResizers(array $items) {
    $result = array();
    foreach ($items as $item) {
      $column = $item->getColumn();

      $resizer_item = null;
      if ($column->getIsResizable()) {
        $resizer_item = $this->newResizerItem();
        $item->setResizerItem($resizer_item);

        $resizer_item
          ->getColumn()
          ->setIsVisible($column->getIsVisible());
      }

      if (!$resizer_item) {
        $result[] = $item;
      } else if ($item->getIsRightAligned()) {
        $result[] = $resizer_item;
        $result[] = $item;
      } else {
        $result[] = $item;
        $result[] = $resizer_item;
      }
    }

    return $result;
  }

  private function generateExpanders(array $items) {
    foreach ($items as $item) {
      $control_item = $item->getControlItem();
      if ($control_item) {
        $expander = $this->newColumnExpanderView();

        $expander->setColumnItem($item);
        $item->setExpander($expander);

        $control_item->appendExpander($expander);
      }
    }

    return $items;
  }

}
