<?php

final class PHUIActionPanelView extends AphrontTagView {

  private $href;
  private $fontIcon;
  private $header;
  private $subHeader;
  private $bigText;
  private $state;
  private $status;

  const COLOR_RED = 'phui-action-panel-red';
  const COLOR_ORANGE = 'phui-action-panel-orange';
  const COLOR_YELLOW = 'phui-action-panel-yellow';
  const COLOR_GREEN = 'phui-action-panel-green';
  const COLOR_BLUE = 'phui-action-panel-blue';
  const COLOR_INDIGO = 'phui-action-panel-indigo';
  const COLOR_VIOLET = 'phui-action-panel-violet';
  const COLOR_PINK = 'phui-action-panel-pink';

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setIcon($image) {
    $this->fontIcon = $image;
    return $this;
  }

  public function setBigText($text) {
    $this->bigText = $text;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setSubHeader($sub) {
    $this->subHeader = $sub;
    return $this;
  }

  public function setState($state) {
    $this->state = $state;
    return $this;
  }

  public function setStatus($text) {
    $this->status = $text;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-action-panel-css');

    $classes = array();
    $classes[] = 'phui-action-panel';
    if ($this->state) {
      $classes[] = $this->state;
    }
    if ($this->bigText) {
      $classes[] = 'phui-action-panel-bigtext';
    }

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {

    $icon = null;
    if ($this->fontIcon) {
      $fonticon = id(new PHUIIconView())
        ->setIcon($this->fontIcon);
      $icon = phutil_tag(
        'span',
        array(
          'class' => 'phui-action-panel-icon',
        ),
        $fonticon);
    }

    $header = null;
    if ($this->header) {
      $header = phutil_tag(
        'span',
        array(
          'class' => 'phui-action-panel-header',
        ),
        $this->header);
    }

    $subheader = null;
    if ($this->subHeader) {
      $subheader = phutil_tag(
        'span',
        array(
          'class' => 'phui-action-panel-subheader',
        ),
        $this->subHeader);
    }

    $row = phutil_tag(
      'span',
      array(
        'class' => 'phui-action-panel-row',
      ),
      array(
        $icon,
        $subheader,
      ));

    $table = phutil_tag(
      'span',
      array(
        'class' => 'phui-action-panel-table',
      ),
      $row);

    return phutil_tag(
      'a',
      array(
        'href' => $this->href,
        'class' => 'phui-action-panel-hitarea',
      ),
      array($header, $table));

  }

}
