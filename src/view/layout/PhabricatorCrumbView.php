<?php

final class PhabricatorCrumbView extends AphrontView {

  private $name;
  private $href;
  private $icon;
  private $isLastCrumb;
  private $rawName;

  /**
   * Allows for custom HTML inside the name field.
   *
   * NOTE: you must handle escaping user text if you use this method.
   */
  public function setRawName($raw_name) {
    $this->rawName = $raw_name;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getNameForRender() {
    return nonempty($this->rawName, phutil_escape_html($this->name));
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  protected function canAppendChild() {
    return false;
  }

  public function setIsLastCrumb($is_last_crumb) {
    $this->isLastCrumb = $is_last_crumb;
    return $this;
  }

  public function render() {
    $classes = array(
      'phabricator-crumb-view',
    );

    $icon = null;
    if ($this->icon) {
      $classes[] = 'phabricator-crumb-has-icon';
      $icon = phutil_render_tag(
        'span',
        array(
          'class' => 'phabricator-crumb-icon '.
                     'sprite-apps-large app-'.$this->icon.'-dark-large',
        ),
        '');
    }

    $name = phutil_render_tag(
      'span',
      array(
        'class' => 'phabricator-crumb-name',
      ),
      $this->getNameForRender());

    $divider = null;
    if (!$this->isLastCrumb) {
      $divider = phutil_render_tag(
        'span',
        array(
          'class' => 'sprite-menu phabricator-crumb-divider',
        ),
        '');
    }

    return phutil_render_tag(
      $this->href ? 'a' : 'span',
      array(
        'href'  => $this->href,
        'class' => implode(' ', $classes),
      ),
      $icon.$name.$divider);
  }


}
