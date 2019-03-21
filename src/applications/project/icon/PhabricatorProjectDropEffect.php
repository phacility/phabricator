<?php

final class PhabricatorProjectDropEffect
  extends Phobject {

  private $icon;
  private $color;
  private $content;
  private $conditions = array();
  private $isTriggerEffect;
  private $isHeader;

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function toDictionary() {
    return array(
      'icon' => $this->getIcon(),
      'color' => $this->getColor(),
      'content' => hsprintf('%s', $this->getContent()),
      'isTriggerEffect' => $this->getIsTriggerEffect(),
      'isHeader' => $this->getIsHeader(),
      'conditions' => $this->getConditions(),
    );
  }

  public function addCondition($field, $operator, $value) {
    $this->conditions[] = array(
      'field' => $field,
      'operator' => $operator,
      'value' => $value,
    );

    return $this;
  }

  public function getConditions() {
    return $this->conditions;
  }

  public function setIsTriggerEffect($is_trigger_effect) {
    $this->isTriggerEffect = $is_trigger_effect;
    return $this;
  }

  public function getIsTriggerEffect() {
    return $this->isTriggerEffect;
  }

  public function setIsHeader($is_header) {
    $this->isHeader = $is_header;
    return $this;
  }

  public function getIsHeader() {
    return $this->isHeader;
  }

}
