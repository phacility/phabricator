<?php

final class PhabricatorPhameApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phame');
  }

  public function getBaseURI() {
    return '/phame/';
  }

  public function getIcon() {
    return 'fa-feed';
  }

  public function getShortDescription() {
    return pht('Internal and External Blogs');
  }

  public function getTitleGlyph() {
    return "\xe2\x9c\xa9";
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Phame User Guide'),
        'href' => PhabricatorEnv::getDoclink('Phame User Guide'),
      ),
    );
  }

  public function getRoutes() {
    return array(
      '/J(?P<id>[1-9]\d*)' => 'PhamePostViewController',
      '/phame/' => array(
        '' => 'PhameHomeController',

        // NOTE: The live routes include an initial "/", so leave it off
        // this route.
        '(?P<live>live)/(?P<blogID>\d+)' => $this->getLiveRoutes(),
        'post/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhamePostListController',
          'blogger/(?P<bloggername>[\w\.-_]+)/' => 'PhamePostListController',
          $this->getEditRoutePattern('edit/')
            => 'PhamePostEditController',
          'history/(?P<id>\d+)/' => 'PhamePostHistoryController',
          'view/(?P<id>\d+)/(?:(?P<slug>[^/]+)/)?' => 'PhamePostViewController',
          '(?P<action>publish|unpublish)/(?P<id>\d+)/'
            => 'PhamePostPublishController',
          'preview/' => 'PhabricatorMarkupPreviewController',
          'move/(?P<id>\d+)/' => 'PhamePostMoveController',
          'archive/(?P<id>\d+)/' => 'PhamePostArchiveController',
          'header/(?P<id>[1-9]\d*)/' => 'PhamePostHeaderPictureController',
        ),
        'blog/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhameBlogListController',
          'archive/(?P<id>[^/]+)/' => 'PhameBlogArchiveController',
          $this->getEditRoutePattern('edit/')
            => 'PhameBlogEditController',
          'view/(?P<blogID>\d+)/' => 'PhameBlogViewController',
          'manage/(?P<id>[^/]+)/' => 'PhameBlogManageController',
          'feed/(?P<id>[^/]+)/' => 'PhameBlogFeedController',
          'picture/(?P<id>[1-9]\d*)/' => 'PhameBlogProfilePictureController',
          'header/(?P<id>[1-9]\d*)/' => 'PhameBlogHeaderPictureController',
        ),
      ),
    );
  }

  public function getBlogRoutes() {
    return $this->getLiveRoutes() + array(
      '/status/' => 'PhabricatorStatusController',
      '/favicon.ico' => 'PhabricatorFaviconController',
      '/robots.txt' => 'PhabricatorRobotsBlogController',
    );
  }

  private function getLiveRoutes() {
    return array(
      '/' => array(
        '' => 'PhameBlogViewController',
        'post/(?P<id>\d+)/(?:(?P<slug>[^/]+)/)?' => 'PhamePostViewController',
      ),

    );
  }

  public function getQuicksandURIPatternBlacklist() {
    return array(
      '/phame/live/.*',
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhamePostRemarkupRule(),
    );
  }


  protected function getCustomCapabilities() {
    return array(
      PhameBlogCreateCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_USER,
        'caption' => pht('Default create policy for blogs.'),
      ),
    );
  }

}
