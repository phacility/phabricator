<?php

final class PhabricatorActionHeaderView extends AphrontView {

  const HEADER_GREY = 'grey';
  const HEADER_DARK_GREY = 'dark-grey';
  const HEADER_BLUE = 'blue';
  const HEADER_GREEN = 'green';
  const HEADER_RED = 'red';
  const HEADER_YELLOW = 'yellow';
  const HEADER_LIGHTBLUE ='lightblue';

  private $headerTitle;
  private $headerHref;
  private $headerIcon;
  private $headerSigils = array();
  private $actions = array();
  private $headerColor;
  private $tag = null;
  private $dropdown;

  public function setDropdown($dropdown) {
    $this->dropdown = $dropdown;
    return $this;
  }

  public function addAction(PHUIIconView $action) {
    $this->actions[] = $action;
    return $this;
  }

  public function setTag(PHUITagView $tag) {
    $this->tag = $tag;
    return $this;
  }

  public function setHeaderTitle($header) {
    $this->headerTitle = $header;
    return $this;
  }

  public function setHeaderHref($href) {
    $this->headerHref = $href;
    return $this;
  }

  public function addHeaderSigil($sigil) {
    $this->headerSigils[] = $sigil;
    return $this;
  }

  public function setHeaderIcon($minicon) {
    $this->headerIcon = $minicon;
    return $this;
  }

  public function setHeaderColor($color) {
    $this->headerColor = $color;
    return $this;
  }

  private function getIconColor() {
    switch ($this->headerColor) {
      case self::HEADER_GREY:
        return 'lightgreytext';
      case self::HEADER_DARK_GREY:
        return 'lightgreytext';
      case self::HEADER_BLUE:
        return 'white';
      case self::HEADER_GREEN:
        return 'white';
      case self::HEADER_RED:
        return 'white';
      case self::HEADER_YELLOW:
        return 'white';
      case self::HEADER_LIGHTBLUE:
        return 'bluegrey';
    }
  }

  public function render() {

    require_celerity_resource('phabricator-action-header-view-css');

    $classes = array();
    $classes[] = 'phabricator-action-header';

    if ($this->headerColor) {
      $classes[] = 'sprite-gradient';
      $classes[] = 'gradient-'.$this->headerColor.'-header';
    }

    if ($this->dropdown) {
      $classes[] = 'dropdown';
    }

    $action_list = array();
    if (nonempty($this->actions)) {
      foreach ($this->actions as $action) {
        $action->addClass($this->getIconColor());
        $action_list[] = phutil_tag(
          'li',
            array(
            'class' => 'phabricator-action-header-icon-item'
          ),
          $action);
      }
    }

    if ($this->tag) {
      $action_list[] = phutil_tag(
        'li',
          array(
          'class' => 'phabricator-action-header-icon-item'
        ),
        $this->tag);
    }

    $header_icon = null;
    if ($this->headerIcon) {
      require_celerity_resource('sprite-minicons-css');
      $header_icon = phutil_tag(
        'span',
          array(
            'class' => 'sprite-minicons minicons-'.$this->headerIcon
          ),
          '');
    }

    $header_title = $this->headerTitle;
    if ($this->headerHref) {
      $header_title = javelin_tag(
        'a',
          array(
            'class' => 'phabricator-action-header-link',
            'href' => $this->headerHref,
            'sigil' => implode(' ', $this->headerSigils)
          ),
          $this->headerTitle);
    }

    $header = phutil_tag(
      'h3',
        array(
          'class' => 'phabricator-action-header-title'
        ),
      array(
        $header_icon,
        $header_title));

    $icons = '';
    if (nonempty($action_list)) {
      $icons = phutil_tag(
        'ul',
          array(
            'class' => 'phabricator-action-header-icon-list'
          ),
          $action_list);
    }

    return phutil_tag(
      'div',
        array(
          'class' => implode(' ', $classes)
        ),
        array(
          $header,
          $icons
        ));
  }
}
