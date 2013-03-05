<?php

abstract class AphrontBarView extends AphrontView {

  private $color;
  private $caption = '';

  const COLOR_DEFAULT   = 'default';
  const COLOR_WARNING   = 'warning';
  const COLOR_DANGER    = 'danger';

  const COLOR_AUTO_BADNESS  = 'auto_badness';   // more = bad!  :(
  const COLOR_AUTO_GOODNESS = 'auto_goodness';  // more = good! :)

  const THRESHOLD_DANGER  = 0.85;
  const THRESHOLD_WARNING = 0.75;

  abstract protected function getRatio();

  abstract protected function getDefaultColor();

  final public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  final public function setCaption($text) {
    $this->caption = $text;
    return $this;
  }

  final protected function getColor() {
    $color = $this->color;
    if (!$color) {
      $color = $this->getDefaultColor();
    }

    switch ($color) {
      case self::COLOR_DEFAULT:
      case self::COLOR_WARNING:
      case self::COLOR_DANGER:
        return $color;
    }

    $ratio = $this->getRatio();
    if ($color === self::COLOR_AUTO_GOODNESS) {
      $ratio = 1.0 - $ratio;
    }

    if ($ratio >= self::THRESHOLD_DANGER) {
      return self::COLOR_DANGER;
    } else if ($ratio >= self::THRESHOLD_WARNING) {
      return self::COLOR_WARNING;
    } else {
      return self::COLOR_DEFAULT;
    }
  }

  final protected function getCaption() {
    return $this->caption;
  }

}
