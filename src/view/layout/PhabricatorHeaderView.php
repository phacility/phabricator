<?php

final class PhabricatorHeaderView extends AphrontView {

  private $objectName;
  private $header;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setObjectName($object_name) {
    $this->objectName = $object_name;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-header-view-css');

    $header = phutil_escape_html($this->header);

    if ($this->objectName) {
      $header = phutil_render_tag(
        'a',
        array(
          'href' => '/'.$this->objectName,
        ),
        phutil_escape_html($this->objectName)).' '.$header;
    }

    return phutil_render_tag(
      'h1',
      array(
        'class' => 'phabricator-header-view',
      ),
      $header);
  }


}
