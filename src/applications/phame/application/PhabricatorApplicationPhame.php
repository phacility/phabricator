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
        ''                          => 'PhameAllPostListController',
        'post/' => array(
          ''                        => 'PhameUserPostListController',
          'delete/(?P<phid>[^/]+)/' => 'PhamePostDeleteController',
          'edit/(?P<phid>[^/]+)/'   => 'PhamePostEditController',
          'new/'                    => 'PhamePostEditController',
          'preview/'                => 'PhamePostPreviewController',
          'view/(?P<phid>[^/]+)/'   => 'PhamePostViewController',
        ),
        'draft/' => array(
          ''                        => 'PhameDraftListController',
          'new/'                    => 'PhamePostEditController',
        ),
        'blog/' => array(
          ''                         => 'PhameUserBlogListController',
          'all/'                     => 'PhameAllBlogListController',
          'new/'                     => 'PhameBlogEditController',
          'delete/(?P<phid>[^/]+)/'  => 'PhameBlogDeleteController',
          'edit/(?P<phid>[^/]+)/'    => 'PhameBlogEditController',
          'view/(?P<phid>[^/]+)/'    => 'PhameBlogViewController',
        ),
        'posts/' => array(
          ''                        => 'PhameUserPostListController',
          '(?P<bloggername>\w+)/'   => 'PhameBloggerPostListController',
          '(?P<bloggername>\w+)/(?P<phametitle>.+/)'
                                    => 'PhamePostViewController',
        ),
      ),
    );
  }
}
