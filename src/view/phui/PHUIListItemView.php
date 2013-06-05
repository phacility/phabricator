<?php

final class PHUIListItemView extends AphrontTagView {

  const TYPE_LINK     = 'type-link';
  const TYPE_SPACER   = 'type-spacer';
  const TYPE_LABEL    = 'type-label';
  const TYPE_BUTTON   = 'type-button';
  const TYPE_CUSTOM   = 'type-custom';
  const TYPE_DIVIDER  = 'type-divider';
  const TYPE_ICON     = 'type-icon';

  private $name;
  private $href;
  private $type = self::TYPE_LINK;
  private $isExternal;
  private $key;
  private $icon;
  private $selected;
  private $containerAttrs;

  public function setProperty($property) {
    $this->property = $property;
    return $this;
  }

  public function getProperty() {
    return $this->property;
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

  // Maybe should be add ?
  public function setContainerAttrs($attrs) {
    $this->containerAttrs = $attrs;
    return $this;
  }

  protected function getTagName() {
    return $this->href ? 'a' : 'div';
  }

  protected function renderTagContainer($tag) {
    $classes = array(
      'phui-list-item-view',
      'phui-list-item-'.$this->type,
      $this->icon ? 'phui-list-item-has-icon' : null,
      $this->selected ? 'phui-list-item-selected' : null
    );

    // This is derptastical
    $this->containerAttrs['class'] = implode(' ', array_filter($classes));

    return phutil_tag('li', $this->containerAttrs, $tag);
  }

  protected function getTagAttributes() {
    return array(
      'class' => $this->href ? 'phui-list-item-href' : '',
      'href' => $this->href);
  }

  protected function getTagContent() {
    $name = null;
    $icon = null;

    if ($this->name) {
      $external = null;
      if ($this->isExternal) {
        $external = " \xE2\x86\x97";
      }

      $name = phutil_tag(
        'span',
        array(
          'class' => 'phui-list-item-name',
        ),
        array(
          $this->name,
          $external,
        ));
    }

    if ($this->icon) {
      $icon = id(new PHUIIconView())
        ->addClass('phui-list-item-icon')
        ->setSpriteSheet(PHUIIconView::SPRITE_ICONS)
        ->setSpriteIcon($this->icon);
    }

    return array(
      $icon,
      $this->renderChildren(),
      $name,
    );
  }

}
