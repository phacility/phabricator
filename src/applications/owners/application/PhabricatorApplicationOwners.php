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

final class PhabricatorApplicationOwners extends PhabricatorApplication {

  public function getBaseURI() {
    return '/owners/';
  }

  public function getAutospriteName() {
    return 'owners';
  }

  public function getShortDescription() {
    return 'Group Source Code';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x81";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Owners_Tool_User_Guide.html');
  }

  public function getFlavorText() {
    return pht('Adopt today!');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRoutes() {
    return array(
      '/owners/' => array(
        '' => 'PhabricatorOwnersListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorOwnersListController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersEditController',
        'new/' => 'PhabricatorOwnersEditController',
        'package/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersDetailController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersDeleteController',
      ),
    );
  }

}
