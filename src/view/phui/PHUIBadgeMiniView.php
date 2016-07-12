<?php

final class PHUIBadgeMiniView extends AphrontTagView {

  private $href;
  private $icon;
  private $quality;
  private $header;
  private $tipDirection;

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setQuality($quality) {
    $this->quality = $quality;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setTipDirection($direction) {
    $this->tipDirection = $direction;
    return $this;
  }

  protected function getTagName() {
    if ($this->href) {
      return 'a';
    } else {
      return 'span';
    }
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-badge-view-css');
    Javelin::initBehavior('phabricator-tooltips');

    $classes = array();
    $classes[] = 'phui-badge-mini';
    if ($this->quality) {
      $quality_color = PhabricatorBadgesQuality::getQualityColor(
        $this->quality);
      $classes[] = 'phui-badge-mini-'.$quality_color;
    }

    return array(
      'class' => implode(' ', $classes),
      'sigil' => 'has-tooltip',
      'href'  => $this->href,
      'meta'  => array(
        'tip' => $this->header,
        'align' => $this->tipDirection,
        'size' => 300,
      ),
    );
  }

  protected function getTagContent() {
    return id(new PHUIIconView())
      ->setIcon($this->icon);
  }

}
