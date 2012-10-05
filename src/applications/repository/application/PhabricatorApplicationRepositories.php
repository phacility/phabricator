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

final class PhabricatorApplicationRepositories extends PhabricatorApplication {

  public function getBaseURI() {
    return '/repository/';
  }

  public function getAutospriteName() {
    return 'repositories';
  }

  public function getShortDescription() {
    return 'Track Repositories';
  }

  public function getTitleGlyph() {
    return "rX";
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      '/repository/' => array(
        ''                     => 'PhabricatorRepositoryListController',
        'create/'              => 'PhabricatorRepositoryCreateController',
        'edit/(?P<id>[1-9]\d*)/(?:(?P<view>\w+)/)?' =>
          'PhabricatorRepositoryEditController',
        'delete/(?P<id>[1-9]\d*)/'  => 'PhabricatorRepositoryDeleteController',
        'project/edit/(?P<id>[1-9]\d*)/' =>
          'PhabricatorRepositoryArcanistProjectEditController',
        'project/delete/(?P<id>[1-9]\d*)/' =>
          'PhabricatorRepositoryArcanistProjectDeleteController',
      ),
    );
  }

}
