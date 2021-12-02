<?php

final class ConduitAPIDocumentationPage
  extends Phobject {

  private $name;
  private $anchor;
  private $iconIcon;
  private $content = array();

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setAnchor($anchor) {
    $this->anchor = $anchor;
    return $this;
  }

  public function getAnchor() {
    return $this->anchor;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function setIconIcon($icon_icon) {
    $this->iconIcon = $icon_icon;
    return $this;
  }

  public function getIconIcon() {
    return $this->iconIcon;
  }

  public function newView() {
    $anchor_name = $this->getAnchor();
    $anchor_view = id(new PhabricatorAnchorView())
      ->setAnchorName($anchor_name);

    $content = $this->content;

    return array(
      $anchor_view,
      $content,
    );
  }


}
