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

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function getCount() {
    return $this->count;
  }

  public function render() {
    $classes = array(
      'phabricator-application-status',
      'phabricator-application-status-type-'.$this->type,
    );

    return phutil_render_tag(
      'span',
      array(
        'class' => implode(' ', $classes),
      ),
      phutil_escape_html($this->text));
  }

}
