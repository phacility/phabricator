<?php

final class PhabricatorContentSourceView extends AphrontView {

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getSourceName() {
    return $this->contentSource->getSourceName();
  }

  public function render() {
    require_celerity_resource('phabricator-content-source-view-css');

    $name = $this->getSourceName();
    if ($name === null) {
      return null;
    }

    return phutil_tag(
      'span',
      array(
        'class' => 'phabricator-content-source-view',
      ),
      pht('Via %s', $name));
  }

}
