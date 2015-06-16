<?php

final class PhabricatorContentSourceView extends AphrontView {

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-content-source-view-css');

    $map = PhabricatorContentSource::getSourceNameMap();

    $source = $this->contentSource->getSource();
    $type = idx($map, $source, null);

    if (!$type) {
      return null;
    }

    return phutil_tag(
      'span',
      array(
        'class' => 'phabricator-content-source-view',
      ),
      pht('Via %s', $type));
  }

}
