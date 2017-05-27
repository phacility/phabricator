<?php

final class PHUIIconCircleView extends AphrontTagView {

  private $href = null;
  private $icon;
  private $color;
  private $size;
  private $state;

  const SMALL = 'circle-small';
  const MEDIUM = 'circle-medium';

  const STATE_FAIL = 'fa-times-circle';
  const STATE_INFO = 'fa-info-circle';
  const STATE_STOP = 'fa-stop-circle';
  const STATE_START = 'fa-play-circle';
  const STATE_PAUSE = 'fa-pause-circle';
  const STATE_SUCCESS = 'fa-check-circle';
  const STATE_WARNING = 'fa-exclamation-circle';
  const STATE_PLUS = 'fa-plus-circle';
  const STATE_MINUS = 'fa-minus-circle';
  const STATE_UNKNOWN = 'fa-question-circle';

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function setSize($size) {
    $this->size = $size;
    return $this;
  }

  public function setState($state) {
    $this->state = $state;
    return $this;
  }

  protected function getTagName() {
    $tag = 'span';
    if ($this->href) {
      $tag = 'a';
    }
    return $tag;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-icon-view-css');

    $classes = array();
    $classes[] = 'phui-icon-circle';

    if ($this->color) {
      $classes[] = 'hover-'.$this->color;
    } else {
      $classes[] = 'hover-sky';
    }

    if ($this->size) {
      $classes[] = $this->size;
    }

    if ($this->state) {
      $classes[] = 'phui-icon-circle-state';
    }

    return array(
      'href' => $this->href,
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    $state = null;
    if ($this->state) {
      $state = id(new PHUIIconView())
        ->setIcon($this->state.' '.$this->color)
        ->addClass('phui-icon-circle-state-icon');
    }

    return id(new PHUIIconView())
      ->setIcon($this->icon)
      ->addClass('phui-icon-circle-icon')
      ->appendChild($state);
  }

  public static function getStateMap() {
    return array(
      self::STATE_FAIL => pht('Failure'),
      self::STATE_INFO => pht('Information'),
      self::STATE_STOP => pht('Stop'),
      self::STATE_START => pht('Start'),
      self::STATE_PAUSE => pht('Pause'),
      self::STATE_SUCCESS => pht('Success'),
      self::STATE_WARNING => pht('Warning'),
      self::STATE_PLUS => pht('Plus'),
      self::STATE_MINUS => pht('Minus'),
      self::STATE_UNKNOWN => pht('Unknown'),
    );
  }

}
