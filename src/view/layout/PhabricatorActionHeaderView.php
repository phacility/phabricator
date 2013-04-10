<?php

final class PhabricatorActionHeaderView extends AphrontView {

  const ICON_GREY = 'grey';
  const ICON_WHITE = 'white';

  const HEADER_GREY = 'grey';
  const HEADER_DARK_GREY = 'dark-grey';
  const HEADER_BLUE = 'blue';
  const HEADER_GREEN = 'green';
  const HEADER_RED = 'red';
  const HEADER_YELLOW = 'yellow';

  private $headerTitle;
  private $headerHref;
  private $headerIcon;
  private $actions = array();
  private $iconColor = PhabricatorActionHeaderView::ICON_GREY;
  private $headerColor;

  public function addAction(PhabricatorActionIconView $action) {
    $this->actions[] = $action;
    return $this;
  }

  public function setTag(PhabricatorTagView $tag) {
    $this->actions[] = $tag;
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

  public function setHeaderIcon($minicon) {
    $this->headerIcon = $minicon;
    return $this;
  }

  public function setIconColor($color) {
    $this->iconColor = $color;
    return $this;
  }

  public function setHeaderColor($color) {
    $this->headerColor = $color;
    return $this;
  }

  public function render() {

    require_celerity_resource('phabricator-action-header-view-css');

    $classes = array();
    $classes[] = 'phabricator-action-header';

    if ($this->headerColor) {
      $classes[] = 'sprite-gradient';
      $classes[] = 'gradient-'.$this->headerColor.'-header';
    }

    $action_list = array();
    foreach ($this->actions as $action) {
      $action_list[] = phutil_tag(
        'li',
          array(
          'class' => 'phabricator-action-header-icon-item'
        ),
        $action);
    }

    $header_icon = '';
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
      $header_title = phutil_tag(
        'a',
          array(
            'class' => 'phabricator-action-header-link',
            'href' => $this->headerHref
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
    if (!empty($action_list)) {
      $classes[] = 'phabricator-action-header-icon-'.$this->iconColor;
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
