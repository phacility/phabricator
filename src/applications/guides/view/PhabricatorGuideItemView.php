<?php

final class PhabricatorGuideItemView extends Phobject {

  private $title;
  private $href;
  private $description;
  private $icon;
  private $iconBackground;
  private $skipHref;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setIconBackground($background) {
    $this->iconBackground = $background;
    return $this;
  }

  public function setSkipHref($href) {
    $this->skipHref = $href;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function getDescription() {
    return $this->description;
  }

  public function getHref() {
    return $this->href;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function getIconBackground() {
    return $this->iconBackground;
  }

  public function getSkipHref() {
    return $this->skipHref;
  }


}
