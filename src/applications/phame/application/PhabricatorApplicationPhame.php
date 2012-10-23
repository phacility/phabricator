<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorApplicationPhame extends PhabricatorApplication {

  public function getBaseURI() {
    return '/phame/';
  }

  public function getAutospriteName() {
    return 'phame';
  }

  public function getShortDescription() {
    return 'Blog';
  }

  public function getTitleGlyph() {
    return "\xe2\x9c\xa9";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Phame_User_Guide.html');
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getRoutes() {
    return array(
     '/phame/' => array(
        '' => 'PhamePostListController',
        'r/(?P<id>\d+)/(?P<hash>[^/]+)/(?P<name>.*)'
                                          => 'PhameResourceController',

        'live/(?P<id>[^/]+)/(?P<more>.*)' => 'PhameBlogLiveController',
        'post/' => array(
          '(?:(?P<filter>draft|all)/)?'     => 'PhamePostListController',
          'blogger/(?P<bloggername>[\w\.-_]+)/' => 'PhamePostListController',
          'delete/(?P<id>[^/]+)/'           => 'PhamePostDeleteController',
          'edit/(?:(?P<id>[^/]+)/)?'        => 'PhamePostEditController',
          'view/(?P<id>\d+)/'               => 'PhamePostViewController',
          'publish/(?P<id>\d+)/'            => 'PhamePostPublishController',
          'unpublish/(?P<id>\d+)/'          => 'PhamePostUnpublishController',
          'notlive/(?P<id>\d+)/'            => 'PhamePostNotLiveController',
          'preview/'                        => 'PhamePostPreviewController',
          'framed/(?P<id>\d+)/'             => 'PhamePostFramedController',
          'new/'                            => 'PhamePostNewController',
          'move/(?P<id>\d+)/'               => 'PhamePostNewController'
        ),
        'blog/' => array(
          '(?:(?P<filter>user|all)/)?'      => 'PhameBlogListController',
          'delete/(?P<id>[^/]+)/'           => 'PhameBlogDeleteController',
          'edit/(?P<id>[^/]+)/'             => 'PhameBlogEditController',
          'view/(?P<id>[^/]+)/'             => 'PhameBlogViewController',
          'new/'                            => 'PhameBlogEditController',
        ),
        'posts/' => array(
          '(?P<bloggername>\w+)/(?P<phametitle>.+/)'
                                    => 'PhamePostViewController',
        ),
      ),
    );
  }
}
