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
        '' => 'PhamePostListController',
        'r/(?P<id>\d+)/(?P<hash>[^/]+)/(?P<name>.*)'
          => 'PhameResourceController',

        'live/(?P<id>[^/]+)/(?P<more>.*)' => 'PhameBlogLiveController',
        'post/' => array(
          '(?:(?P<filter>draft|all)/)?' => 'PhamePostListController',
          'blogger/(?P<bloggername>[\w\.-_]+)/' => 'PhamePostListController',
          'delete/(?P<id>[^/]+)/' => 'PhamePostDeleteController',
          'edit/(?:(?P<id>[^/]+)/)?' => 'PhamePostEditController',
          'view/(?P<id>\d+)/' => 'PhamePostViewController',
          'publish/(?P<id>\d+)/' => 'PhamePostPublishController',
          'unpublish/(?P<id>\d+)/' => 'PhamePostUnpublishController',
          'notlive/(?P<id>\d+)/' => 'PhamePostNotLiveController',
          'preview/' => 'PhamePostPreviewController',
          'framed/(?P<id>\d+)/' => 'PhamePostFramedController',
          'new/' => 'PhamePostNewController',
          'move/(?P<id>\d+)/' => 'PhamePostNewController',
        ),
        'blog/' => array(
          '(?:(?P<filter>user|all)/)?' => 'PhameBlogListController',
          'delete/(?P<id>[^/]+)/' => 'PhameBlogDeleteController',
          'edit/(?P<id>[^/]+)/' => 'PhameBlogEditController',
          'view/(?P<id>[^/]+)/' => 'PhameBlogViewController',
          'feed/(?P<id>[^/]+)/' => 'PhameBlogFeedController',
          'new/' => 'PhameBlogEditController',
        ),
      ),
    );
  }

  public function getQuicksandURIPatternBlacklist() {
    return array(
      '/phame/live/.*',
    );
  }

}
