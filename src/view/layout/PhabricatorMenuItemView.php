<?php

final class PhabricatorMenuItemView extends AphrontView {

  const TYPE_LINK     = 'type-link';
  const TYPE_SPACER   = 'type-spacer';
  const TYPE_LABEL    = 'type-label';

  private $name;
  private $href;
  private $type = self::TYPE_LINK;
  private $isExternal;
  private $key;
  private $classes = array();
  private $workflow;
  private $sortOrder = 1.0;
  private $icon;
  private $selected;
  private $sigils = array();
  private $metadata;
  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setProperty($property) {
    $this->property = $property;
    return $this;
  }

  public function getProperty() {
    return $this->property;
  }

  public function setMetadata($metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  public function addSigil($sigil) {
    $this->sigils[] = $sigil;
    return $this;
  }

  public function setSelected($selected) {
    $this->selected = $selected;
    return $this;
  }

  public function getSelected() {
    return $this->selected;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setKey($key) {
    $this->key = (string)$key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIsExternal($is_external) {
    $this->isExternal = $is_external;
    return $this;
  }

  public function getIsExternal() {
    return $this->isExternal;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function setSortOrder($order) {
    $this->sortOrder = $order;
    return $this;
  }

  public function getSortOrder() {
    return $this->sortOrder;
  }

  public function render() {
    $classes = array(
      'phabricator-menu-item-view',
      'phabricator-menu-item-'.$this->type,
    );

    $classes = array_merge($classes, $this->classes);

    $name = null;
    if ($this->name) {
      $external = null;
      if ($this->isExternal) {
        $external = " \xE2\x86\x97";
      }
      $name = phutil_render_tag(
        'span',
        array(
          'class' => 'phabricator-menu-item-name',
        ),
        phutil_escape_html($this->name.$external));
    }

    $sigils = $this->sigils;
    if ($this->workflow) {
      $sigils[] = 'workflow';
    }
    if ($sigils) {
      $sigils = implode(' ', $sigils);
    } else {
      $sigils = null;
    }

    return javelin_render_tag(
      $this->href ? 'a' : 'div',
      array(
        'class' => implode(' ', $classes),
        'href'  => $this->href,
        'sigil' => $sigils,
        'meta'  => $this->metadata,
        'id'    => $this->id,
      ),
      $this->renderChildren().
      $name);
  }

}
