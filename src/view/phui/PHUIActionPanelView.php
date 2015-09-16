<?php

final class PHUIActionPanelView extends AphrontTagView {

  private $href;
  private $fontIcon;
  private $header;
  private $subHeader;
  private $bigText;
  private $state;
  private $status;

  const STATE_WARN = 'phui-action-panel-warn';
  const STATE_INFO = 'phui-action-panel-info';
  const STATE_ERROR = 'phui-action-panel-error';
  const STATE_SUCCESS = 'phui-action-panel-success';
  const STATE_PROGRESS = 'phui-action-panel-progress';
  const STATE_NONE = 'phui-action-panel-none';

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setFontIcon($image) {
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
        ->setIconFont($this->fontIcon);
      $icon = phutil_tag(
        'span',
        array(
          'class' => 'phui-action-panel-icon',
        ),
        $fonticon);
    }

    $header = null;
    if ($this->header) {
      $header = $this->header;
      if ($this->href) {
        $header = phutil_tag(
          'a',
          array(
            'href' => $this->href,
          ),
          $this->header);
      }
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phui-action-panel-header',
        ),
        $header);
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

    $content = phutil_tag(
      'a',
      array(
        'href' => $this->href,
        'class' => 'phui-action-panel-hitarea',
      ),
      $table);

    return array($header, $content);

  }

}
