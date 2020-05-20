<?php

final class PHUIFormationView
  extends AphrontAutoIDView {

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

      $classes = array();
      if ($column->getIsDesktopOnly()) {
        $classes[] = 'phui-formation-desktop-only';
      }

      $cells[] = phutil_tag(
        'td',
        array(
          'id' => $item->getID(),
          'style' => implode(' ', $style),
          'class' => implode(' ', $classes),
        ),
        array(
          $column,
          $item->getExpanders(),
        ));
    }

    $phuix_items = array();
    foreach ($items as $item) {
      $phuix_items[] = $item->newClientProperties();
    }

    $table_row = phutil_tag('tr', array(), $cells);
    $table_body = phutil_tag('tbody', array(), $table_row);
    $table = javelin_tag(
      'table',
      array(
        'id' => $this->getID(),
        'class' => 'phui-formation-view',
        'sigil' => 'phuix-formation-view',
        'meta' => array(
          'items' => $phuix_items,
        ),
      ),
      $table_body);

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

        $resizer_item->getColumn()
          ->setIsDesktopOnly($column->getIsDesktopOnly())
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
      if (!$control_item) {
        continue;
      }

      $expander = $this->newColumnExpanderView();

      $tip = $item->getColumn()->getExpanderTooltip();
      $expander->setTooltip($tip);

      $expander->setColumnItem($item);
      $item->setExpander($expander);

      $control_item->appendExpander($expander);
    }

    return $items;
  }

  public function setFooter($footer) {
    foreach ($this->items as $item) {
      if ($item->getColumn() instanceof PHUIFormationContentView) {
        $item->getColumn()->appendChild($footer);
        return $this;
      }
    }

    throw new Exception(
      pht('Unable to find a content column to place the footer inside.'));
  }

}
