<?php

final class PhabricatorApplicationFiles extends PhabricatorApplication {

  public function getBaseURI() {
    return '/file/';
  }

  public function getShortDescription() {
    return 'Store and Share Files';
  }

  public function getAutospriteName() {
    return 'files';
  }

  public function getTitleGlyph() {
    return "\xE2\x87\xAA";
  }

  public function getFlavorText() {
    return pht('Blob store for Pokemon pictures.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/F(?P<id>[1-9]\d*)' => 'PhabricatorFileShortcutController',
      '/file/' => array(
        '' => 'PhabricatorFileListController',
        'filter/(?P<filter>\w+)/' => 'PhabricatorFileListController',
        'upload/' => 'PhabricatorFileUploadController',
        'dropupload/' => 'PhabricatorFileDropUploadController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorFileDeleteController',
        'info/(?P<phid>[^/]+)/' => 'PhabricatorFileInfoController',
        'data/(?P<key>[^/]+)/(?P<phid>[^/]+)/.*'
          => 'PhabricatorFileDataController',
        'proxy/' => 'PhabricatorFileProxyController',
        'xform/(?P<transform>[^/]+)/(?P<phid>[^/]+)/(?P<key>[^/]+)/'
          => 'PhabricatorFileTransformController',
      ),
    );
  }

}
