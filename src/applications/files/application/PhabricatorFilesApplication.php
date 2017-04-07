<?php

final class PhabricatorFilesApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/file/';
  }

  public function getName() {
    return pht('Files');
  }

  public function getShortDescription() {
    return pht('Store and Share Files');
  }

  public function getIcon() {
    return 'fa-file';
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

  public function canUninstall() {
    return false;
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorEmbedFileRemarkupRule(),
      new PhabricatorImageRemarkupRule(),
    );
  }

  public function supportsEmailIntegration() {
    return true;
  }

  public function getAppEmailBlurb() {
    return pht(
      'Send emails with file attachments to these addresses to upload '.
      'files. %s',
      phutil_tag(
        'a',
        array(
          'href' => $this->getInboundEmailSupportLink(),
        ),
        pht('Learn More')));
  }

  protected function getCustomCapabilities() {
    return array(
      FilesDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created files.'),
        'template' => PhabricatorFileFilePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
    );
  }

  public function getRoutes() {
    return array(
      '/F(?P<id>[1-9]\d*)' => 'PhabricatorFileInfoController',
      '/file/' => array(
        '(query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorFileListController',
        'upload/' => 'PhabricatorFileUploadController',
        'dropupload/' => 'PhabricatorFileDropUploadController',
        'compose/' => 'PhabricatorFileComposeController',
        'comment/(?P<id>[1-9]\d*)/' => 'PhabricatorFileCommentController',
        'thread/(?P<phid>[^/]+)/' => 'PhabricatorFileLightboxController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorFileDeleteController',
        $this->getEditRoutePattern('edit/')
          => 'PhabricatorFileEditController',
        'info/(?P<phid>[^/]+)/' => 'PhabricatorFileInfoController',
        'imageproxy/' => 'PhabricatorFileImageProxyController',
        'transforms/(?P<id>[1-9]\d*)/' =>
          'PhabricatorFileTransformListController',
        'uploaddialog/(?P<single>single/)?'
          => 'PhabricatorFileUploadDialogController',
        'download/(?P<phid>[^/]+)/' => 'PhabricatorFileDialogController',
        'iconset/(?P<key>[^/]+)/' => array(
          'select/' => 'PhabricatorFileIconSetSelectController',
        ),
      ) + $this->getResourceSubroutes(),
    );
  }

  public function getResourceRoutes() {
    return array(
      '/file/' => $this->getResourceSubroutes(),
    );
  }

  private function getResourceSubroutes() {
    return array(
      'data/'.
        '(?:@(?P<instance>[^/]+)/)?'.
        '(?P<key>[^/]+)/'.
        '(?P<phid>[^/]+)/'.
        '(?:(?P<token>[^/]+)/)?'.
        '.*'
        => 'PhabricatorFileDataController',
      'xform/'.
        '(?:@(?P<instance>[^/]+)/)?'.
        '(?P<transform>[^/]+)/'.
        '(?P<phid>[^/]+)/'.
        '(?P<key>[^/]+)/'
        => 'PhabricatorFileTransformController',
    );
  }

  public function getMailCommandObjects() {
    return array(
      'file' => array(
        'name' => pht('Email Commands: Files'),
        'header' => pht('Interacting with Files'),
        'object' => new PhabricatorFile(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'files.'),
      ),
    );
  }

  public function getQuicksandURIPatternBlacklist() {
    return array(
      '/file/data/.*',
    );
  }

}
