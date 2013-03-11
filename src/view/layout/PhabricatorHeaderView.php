<?php

final class PhabricatorHeaderView extends AphrontView {

  private $objectName;
  private $header;
  private $tags = array();

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setObjectName($object_name) {
    $this->objectName = $object_name;
    return $this;
  }

  public function addTag(PhabricatorTagView $tag) {
    $this->tags[] = $tag;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-header-view-css');

    $header = array($this->header);

    if ($this->objectName) {
      array_unshift(
        $header,
        phutil_tag(
          'a',
          array(
            'href' => '/'.$this->objectName,
          ),
          $this->objectName),
        ' ');
    }

    if ($this->tags) {
      $header[] = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-header-tags',
        ),
        $this->tags);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-header-shell',
      ),
      phutil_tag(
        'h1',
        array(
          'class' => 'phabricator-header-view',
        ),
        $header));
  }


}
