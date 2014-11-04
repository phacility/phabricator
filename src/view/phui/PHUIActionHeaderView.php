<?php

final class PHUIActionHeaderView extends AphrontView {

  const HEADER_GREY = 'grey';
  const HEADER_DARK_GREY = 'dark-grey';
  const HEADER_LIGHTGREEN = 'lightgreen';
  const HEADER_LIGHTRED = 'lightred';
  const HEADER_LIGHTVIOLET = 'lightviolet';
  const HEADER_LIGHTBLUE ='lightblue';
  const HEADER_WHITE = 'white';

  private $headerTitle;
  private $headerSubtitle;
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

  public function setHeaderSubtitle($subtitle) {
    $this->headerSubtitle = $subtitle;
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

  public function setHeaderIcon(PHUIIconView $icon) {
    $this->headerIcon = $icon;
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
      case self::HEADER_LIGHTGREEN:
        return 'bluegrey';
      case self::HEADER_LIGHTRED:
        return 'bluegrey';
      case self::HEADER_LIGHTVIOLET:
        return 'bluegrey';
      case self::HEADER_LIGHTBLUE:
        return 'bluegrey';
    }
  }

  public function render() {

    require_celerity_resource('phui-action-header-view-css');

    $classes = array();
    $classes[] = 'phui-action-header';

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
            'class' => 'phui-action-header-icon-item',
          ),
          $action);
      }
    }

    if ($this->tag) {
      $action_list[] = phutil_tag(
        'li',
          array(
          'class' => 'phui-action-header-icon-item',
        ),
        $this->tag);
    }

    $header_icon = null;
    if ($this->headerIcon) {
      $header_icon = $this->headerIcon;
    }

    $header_title = $this->headerTitle;
    if ($this->headerHref) {
      $header_title = javelin_tag(
        'a',
          array(
            'class' => 'phui-action-header-link',
            'href' => $this->headerHref,
            'sigil' => implode(' ', $this->headerSigils),
          ),
          $this->headerTitle);
    }

    $header_subtitle = null;
    if ($this->headerSubtitle) {
      $header_subtitle = phutil_tag(
        'span',
          array(
            'class' => 'phui-action-header-subtitle',
          ),
          $this->headerSubtitle);
    }

    $header = phutil_tag(
      'h3',
        array(
          'class' => 'phui-action-header-title',
        ),
      array(
        $header_icon,
        $header_title,
        $header_subtitle,
      ));

    $icons = '';
    if (nonempty($action_list)) {
      $icons = phutil_tag(
        'ul',
          array(
            'class' => 'phui-action-header-icon-list',
          ),
          $action_list);
    }

    return phutil_tag(
      'div',
        array(
          'class' => implode(' ', $classes),
        ),
        array(
          $header,
          $icons,
        ));
  }
}
