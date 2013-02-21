<?php

final class PhabricatorApplicationStatusView extends AphrontView {

  private $count;
  private $text;
  private $type;

  const TYPE_NEEDS_ATTENTION  = 'needs';
  const TYPE_INFO             = 'info';
  const TYPE_OKAY             = 'okay';
  const TYPE_WARNING          = 'warning';
  const TYPE_EMPTY            = 'empty';

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function getText() {
    return $this->text;
  }

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function getCount() {
    return $this->count;
  }

  public function render() {
    $type = $this->type;
    if (!$this->count) {
      $type = self::TYPE_EMPTY;
    }

    $classes = array(
      'phabricator-application-status',
      'phabricator-application-status-type-'.$type,
    );

    return phutil_tag(
      'span',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->text);
  }

}
