<?php

final class PhabricatorProfileMenuItemView
  extends Phobject {

  private $config;
  private $uri;
  private $name;
  private $icon;
  private $iconImage;
  private $disabled;
  private $tooltip;
  private $actions = array();
  private $counts = array();
  private $images = array();
  private $progressBars = array();
  private $isExternalLink;
  private $specialType;

  public function setMenuItemConfiguration(
    PhabricatorProfileMenuItemConfiguration $config) {
    $this->config = $config;
    return $this;
  }

  public function getMenuItemConfiguration() {
    return $this->config;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setIconImage($icon_image) {
    $this->iconImage = $icon_image;
    return $this;
  }

  public function getIconImage() {
    return $this->iconImage;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function setTooltip($tooltip) {
    $this->tooltip = $tooltip;
    return $this;
  }

  public function getTooltip() {
    return $this->tooltip;
  }

  public function newAction($uri) {
    $this->actions[] = $uri;
    return null;
  }

  public function newCount($count) {
    $this->counts[] = $count;
    return null;
  }

  public function newProfileImage($src) {
    $this->images[] = $src;
    return null;
  }

  public function newProgressBar($bar) {
    $this->progressBars[] = $bar;
    return null;
  }

  public function setIsExternalLink($is_external) {
    $this->isExternalLink = $is_external;
    return $this;
  }

  public function getIsExternalLink() {
    return $this->isExternalLink;
  }

  public function setIsLabel() {
    return $this->setSpecialType('label');
  }

  public function getIsLabel() {
    return $this->isSpecialType('label');
  }

  public function setIsDivider() {
    return $this->setSpecialType('divider');
  }

  public function getIsDivider() {
    return $this->isSpecialType('divider');
  }

  private function setSpecialType($type) {
    $this->specialType = $type;
    return $this;
  }

  private function isSpecialType($type) {
    return ($this->specialType === $type);
  }

  public function newListItemView() {
    $view = id(new PHUIListItemView())
      ->setName($this->getName());

    $uri = $this->getURI();
    if ($uri !== null && strlen($uri)) {
      if ($this->getIsExternalLink()) {
        if (!PhabricatorEnv::isValidURIForLink($uri)) {
          $uri = '#';
        }
        $view->setRel('noreferrer');
      }

      $view->setHref($uri);
    }

    $icon = $this->getIcon();
    if ($icon) {
      $view->setIcon($icon);
    }

    $icon_image = $this->getIconImage();
    if ($icon_image) {
      $view->setProfileImage($icon_image);
    }

    if ($this->getDisabled()) {
      $view->setDisabled(true);
    }

    if ($this->getIsLabel()) {
      $view->setType(PHUIListItemView::TYPE_LABEL);
    }

    if ($this->getIsDivider()) {
      $view
        ->setType(PHUIListItemView::TYPE_DIVIDER)
        ->addClass('phui-divider');
    }

    $tooltip = $this->getTooltip();
    if ($tooltip !== null && strlen($tooltip)) {
      $view->setTooltip($tooltip);
    }

    if ($this->images) {
      require_celerity_resource('people-picture-menu-item-css');
      foreach ($this->images as $image_src) {
        $classes = array();
        $classes[] = 'people-menu-image';

        if ($this->getDisabled()) {
          $classes[] = 'phui-image-disabled';
        }

        $image = phutil_tag(
          'img',
          array(
            'src' => $image_src,
            'class' => implode(' ', $classes),
          ));

        $image = phutil_tag(
          'div',
          array(
            'class' => 'people-menu-image-container',
          ),
          $image);

        $view->appendChild($image);
      }
    }

    foreach ($this->counts as $count) {
      $view->appendChild(
        phutil_tag(
          'span',
          array(
            'class' => 'phui-list-item-count',
          ),
          $count));
    }

    foreach ($this->actions as $action) {
      $view->setActionIcon('fa-pencil', $action);
    }

    foreach ($this->progressBars as $bar) {
      $view->appendChild($bar);
    }

    return $view;
  }

}
