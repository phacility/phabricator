<?php

final class PhabricatorContentSourceView extends AphrontView {

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-content-source-view-css');

    $map = array(
      PhabricatorContentSource::SOURCE_WEB      => pht('Web'),
      PhabricatorContentSource::SOURCE_CONDUIT  => pht('Conduit'),
      PhabricatorContentSource::SOURCE_EMAIL    => pht('Email'),
      PhabricatorContentSource::SOURCE_MOBILE   => pht('Mobile'),
      PhabricatorContentSource::SOURCE_TABLET   => pht('Tablet'),
      PhabricatorContentSource::SOURCE_FAX      => pht('Fax'),
      PhabricatorContentSource::SOURCE_LEGACY   => pht('Old World'),
    );

    $source = $this->contentSource->getSource();
    $type = idx($map, $source, null);

    if (!$type) {
      return null;
    }

    return phutil_tag(
      'span',
      array(
        'class' => "phabricator-content-source-view",
      ),
      "Via {$type}");
  }

}
