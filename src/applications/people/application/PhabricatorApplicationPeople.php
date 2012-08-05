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

final class PhabricatorApplicationPeople extends PhabricatorApplication {

  public function getShortDescription() {
    return 'User Accounts';
  }

  public function getBaseURI() {
    return '/people/';
  }

  public function getIconURI() {
    return celerity_get_resource_uri('/rsrc/image/app/app_people.png');
  }

  public function getRoutes() {
    return array(
      '/people/' => array(
        '' => 'PhabricatorPeopleListController',
        'logs/' => 'PhabricatorPeopleLogsController',
        'edit/(?:(?P<id>\d+)/(?:(?P<view>\w+)/)?)?'
          => 'PhabricatorPeopleEditController',
        'ldap/' => 'PhabricatorPeopleLdapController',
      ),
      '/p/(?P<username>[\w._-]+)/(?:(?P<page>\w+)/)?'
        => 'PhabricatorPeopleProfileController',
    );
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller) {

    $items = array();

    if ($user->isLoggedIn()) {
      require_celerity_resource('phabricator-glyph-css');
      $item = new PhabricatorMainMenuIconView();
      $item->setName($user->getUsername());
      $item->addClass('glyph glyph-profile');
      $item->setHref('/p/'.$user->getUsername().'/');
      $item->setSortOrder(0.0);
      $items[] = $item;
    }

    return $items;
  }

}
