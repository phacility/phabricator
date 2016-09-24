<?php

final class PhabricatorPhragmentApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phragment');
  }

  public function getBaseURI() {
    return '/phragment/';
  }

  public function getShortDescription() {
    return pht('Versioned Artifact Storage');
  }

  public function getIcon() {
    return 'fa-floppy-o';
  }

  public function getTitleGlyph() {
    return "\xE2\x96\x9B";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
    return true;
  }

  public function canUninstall() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/phragment/' => array(
        '' => 'PhragmentBrowseController',
        'browse/(?P<dblob>.*)' => 'PhragmentBrowseController',
        'create/(?P<dblob>.*)' => 'PhragmentCreateController',
        'update/(?P<dblob>.+)' => 'PhragmentUpdateController',
        'policy/(?P<dblob>.+)' => 'PhragmentPolicyController',
        'history/(?P<dblob>.+)' => 'PhragmentHistoryController',
        'zip/(?P<dblob>.+)' => 'PhragmentZIPController',
        'zip@(?P<snapshot>[^/]+)/(?P<dblob>.+)' => 'PhragmentZIPController',
        'version/(?P<id>[0-9]*)/' => 'PhragmentVersionController',
        'patch/(?P<aid>[0-9x]*)/(?P<bid>[0-9]*)/' => 'PhragmentPatchController',
        'revert/(?P<id>[0-9]*)/(?P<dblob>.*)' => 'PhragmentRevertController',
        'snapshot/' => array(
          'create/(?P<dblob>.*)' => 'PhragmentSnapshotCreateController',
          'view/(?P<id>[0-9]*)/' => 'PhragmentSnapshotViewController',
          'delete/(?P<id>[0-9]*)/' => 'PhragmentSnapshotDeleteController',
          'promote/' => array(
            'latest/(?P<dblob>.*)' => 'PhragmentSnapshotPromoteController',
            '(?P<id>[0-9]*)/' => 'PhragmentSnapshotPromoteController',
          ),
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhragmentCanCreateCapability::CAPABILITY => array(),
    );
  }

}
