<?php

final class PhabricatorContentSourceView extends AphrontView {

  private $contentSource;
  private $user;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }


  public function render() {
    require_celerity_resource('phabricator-content-source-view-css');

    $map = array(
      PhabricatorContentSource::SOURCE_WEB      => 'Web',
      PhabricatorContentSource::SOURCE_CONDUIT  => 'Conduit',
      PhabricatorContentSource::SOURCE_EMAIL    => 'Email',
      PhabricatorContentSource::SOURCE_MOBILE   => 'Mobile',
      PhabricatorContentSource::SOURCE_TABLET   => 'Tablet',
      PhabricatorContentSource::SOURCE_FAX      => 'Fax',
    );

    $source = $this->contentSource->getSource();
    $type = idx($map, $source, null);

    if (!$type) {
      return;
    }

    return phutil_render_tag(
      'span',
      array(
        'class' => "phabricator-content-source-view",
      ),
      "Via {$type}");
  }

}
