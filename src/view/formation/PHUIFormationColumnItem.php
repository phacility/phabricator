<?php

final class PHUIFormationColumnItem
  extends Phobject {

  private $id;
  private $column;
  private $controlItem;
  private $resizerItem;
  private $isRightAligned;
  private $expander;
  private $expanders = array();

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function setColumn(PHUIFormationColumnView $column) {
    $this->column = $column;
    return $this;
  }

  public function getColumn() {
    return $this->column;
  }

  public function setControlItem(PHUIFormationColumnItem $control_item) {
    $this->controlItem = $control_item;
    return $this;
  }

  public function getControlItem() {
    return $this->controlItem;
  }

  public function setIsRightAligned($is_right_aligned) {
    $this->isRightAligned = $is_right_aligned;
    return $this;
  }

  public function getIsRightAligned() {
    return $this->isRightAligned;
  }

  public function setResizerItem(PHUIFormationColumnItem $resizer_item) {
    $this->resizerItem = $resizer_item;
    return $this;
  }

  public function getResizerItem() {
    return $this->resizerItem;
  }

  public function setExpander(PHUIFormationExpanderView $expander) {
    $this->expander = $expander;
    return $this;
  }

  public function getExpander() {
    return $this->expander;
  }

  public function appendExpander(PHUIFormationExpanderView $expander) {
    $this->expanders[] = $expander;
    return $this;
  }

  public function getExpanders() {
    return $this->expanders;
  }

  public function newClientProperties() {
    $column = $this->getColumn();

    $expander_id = null;

    $expander = $this->getExpander();
    if ($expander) {
      $expander_id = $expander->getID();
    }

    $resizer_details = null;
    $resizer_item = $this->getResizerItem();
    if ($resizer_item) {
      $visible_key = $column->getVisibleSettingKey();
      $width_key = $column->getWidthSettingKey();
      $min_width = $column->getMinimumWidth();
      $max_width = $column->getMaximumWidth();

      $resizer_details = array(
        'itemID' => $resizer_item->getID(),
        'controlID' => $resizer_item->getColumn()->getID(),
        'widthKey' => $width_key,
        'visibleKey' => $visible_key,
        'minimumWidth' => $min_width,
        'maximumWidth' => $max_width,
      );
    }

    $width = $column->getWidth();
    if ($width !== null) {
      $width = (int)$width;
    }

    $is_visible = (bool)$column->getIsVisible();
    $is_right_aligned = $this->getIsRightAligned();

    $column_details = $column->newClientProperties();

    return array(
      'itemID' => $this->getID(),
      'width' => $width,
      'isVisible' => $is_visible,
      'isRightAligned' => $is_right_aligned,
      'expanderID' => $expander_id,
      'resizer' => $resizer_details,
      'column' => $column_details,
    );
  }

}
