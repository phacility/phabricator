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

  public function setKey($key) {
    $this->key = $key;
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

  protected function canAppendChild() {
    return false;
  }

  public function render() {
    $classes = array(
      'phabricator-menu-item-view',
      'phabricator-menu-item-'.$this->type,
    );

    $external = null;
    if ($this->isExternal) {
      $external = " \xE2\x86\x97";
    }

    $classes = array_merge($classes, $this->classes);

    return phutil_render_tag(
      $this->href ? 'a' : 'div',
      array(
        'class' => implode(' ', $classes),
        'href'  => $this->href,
      ),
      phutil_escape_html($this->name.$external));
  }

}
