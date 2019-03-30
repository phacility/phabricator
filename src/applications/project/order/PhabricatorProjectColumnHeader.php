<?php

final class PhabricatorProjectColumnHeader
  extends Phobject {

  private $orderKey;
  private $headerKey;
  private $sortVector;
  private $name;
  private $icon;
  private $editProperties;
  private $dropEffects = array();

  public function setOrderKey($order_key) {
    $this->orderKey = $order_key;
    return $this;
  }

  public function getOrderKey() {
    return $this->orderKey;
  }

  public function setHeaderKey($header_key) {
    $this->headerKey = $header_key;
    return $this;
  }

  public function getHeaderKey() {
    return $this->headerKey;
  }

  public function setSortVector(array $sort_vector) {
    $this->sortVector = $sort_vector;
    return $this;
  }

  public function getSortVector() {
    return $this->sortVector;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIcon(PHUIIconView$icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setEditProperties(array $edit_properties) {
    $this->editProperties = $edit_properties;
    return $this;
  }

  public function getEditProperties() {
    return $this->editProperties;
  }

  public function addDropEffect(PhabricatorProjectDropEffect $effect) {
    $this->dropEffects[] = $effect;
    return $this;
  }

  public function getDropEffects() {
    return $this->dropEffects;
  }

  public function toDictionary() {
    return array(
      'order' => $this->getOrderKey(),
      'key' => $this->getHeaderKey(),
      'template' => hsprintf('%s', $this->newView()),
      'vector' => $this->getSortVector(),
      'editProperties' => $this->getEditProperties(),
      'effects' => mpull($this->getDropEffects(), 'toDictionary'),
    );
  }

  private function newView() {
    $icon_view = $this->getIcon();
    $name = $this->getName();

    $template = phutil_tag(
      'li',
      array(
        'class' => 'workboard-group-header',
      ),
      array(
        $icon_view,
        phutil_tag(
          'span',
          array(
            'class' => 'workboard-group-header-name',
          ),
          $name),
      ));

    return $template;
  }

}
