<?php

final class PhabricatorPhameApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phame');
  }

  public function getBaseURI() {
    return '/phame/';
  }

  public function getFontIcon() {
    return 'fa-star';
  }

  public function getShortDescription() {
    return pht('Blog');
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

  public function isPrototype() {
    return true;
  }

  public function getRoutes() {
    return array(
     '/phame/' => array(
        '' => 'PhameHomeController',
        'live/(?P<id>[^/]+)/(?P<more>.*)' => 'PhameBlogLiveController',
        'post/' => array(
          '(?:(?P<filter>draft|all)/)?' => 'PhamePostListController',
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhamePostListController',
          'blogger/(?P<bloggername>[\w\.-_]+)/' => 'PhamePostListController',
          'edit/(?:(?P<id>[^/]+)/)?' => 'PhamePostEditController',
          'history/(?P<id>\d+)/' => 'PhamePostHistoryController',
          'view/(?P<id>\d+)/' => 'PhamePostViewController',
          'publish/(?P<id>\d+)/' => 'PhamePostPublishController',
          'preview/(?P<id>\d+)/' => 'PhamePostPreviewController',
          'unpublish/(?P<id>\d+)/' => 'PhamePostUnpublishController',
          'notlive/(?P<id>\d+)/' => 'PhamePostNotLiveController',
          'preview/' => 'PhabricatorMarkupPreviewController',
          'framed/(?P<id>\d+)/' => 'PhamePostFramedController',
          'new/' => 'PhamePostNewController',
          'move/(?P<id>\d+)/' => 'PhamePostNewController',
          'comment/(?P<id>[1-9]\d*)/' => 'PhamePostCommentController',
        ),
        'blog/' => array(
          '(?:(?P<filter>user|all)/)?' => 'PhameBlogListController',
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhameBlogListController',
          'archive/(?P<id>[^/]+)/' => 'PhameBlogArchiveController',
          'edit/(?P<id>[^/]+)/' => 'PhameBlogEditController',
          'view/(?P<id>[^/]+)/' => 'PhameBlogViewController',
          'manage/(?P<id>[^/]+)/' => 'PhameBlogManageController',
          'feed/(?P<id>[^/]+)/' => 'PhameBlogFeedController',
          'new/' => 'PhameBlogEditController',
          'picture/(?P<id>[1-9]\d*)/' => 'PhameBlogProfilePictureController',
        ),
      ) + $this->getResourceSubroutes(),
    );
  }

  public function getResourceRoutes() {
    return array(
      '/phame/' => $this->getResourceSubroutes(),
    );
  }

  private function getResourceSubroutes() {
    return array(
      'r/(?P<id>\d+)/(?P<hash>[^/]+)/(?P<name>.*)' =>
        'PhameResourceController',
    );
  }

  public function getBlogRoutes() {
    return array(
      '/(?P<more>.*)' => 'PhameBlogLiveController',
    );
  }

  public function getBlogCDNRoutes() {
    return array(
      '/phame/' => array(
        'r/(?P<id>\d+)/(?P<hash>[^/]+)/(?P<name>.*)' =>
            'PhameResourceController',
      ),
    );
  }

  public function getQuicksandURIPatternBlacklist() {
    return array(
      '/phame/live/.*',
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
