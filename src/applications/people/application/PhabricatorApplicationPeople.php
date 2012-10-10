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

  public function getTitleGlyph() {
    return "\xE2\x99\x9F";
  }

  public function getAutospriteName() {
    return 'people';
  }

  public function getFlavorText() {
    return pht('Sort of a social utility.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      '/people/' => array(
        '' => 'PhabricatorPeopleListController',
        'logs/' => 'PhabricatorPeopleLogsController',
        'edit/(?:(?P<id>[1-9]\d*)/(?:(?P<view>\w+)/)?)?'
          => 'PhabricatorPeopleEditController',
        'ldap/' => 'PhabricatorPeopleLdapController',
      ),
      '/p/(?P<username>[\w._-]+)/(?:(?P<page>\w+)/)?'
        => 'PhabricatorPeopleProfileController',
      '/emailverify/(?P<code>[^/]+)/' =>
        'PhabricatorEmailVerificationController',
    );
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if (($controller instanceof PhabricatorPeopleProfileController) &&
        ($controller->getProfileUser()) &&
        ($controller->getProfileUser()->getPHID() == $user->getPHID())) {
      $class = 'main-menu-item-icon-profile-selected';
    } else {
      $class = 'main-menu-item-icon-profile-not-selected';
    }

    if ($user->isLoggedIn()) {
      $image = $user->loadProfileImageURI();

      $item = new PhabricatorMainMenuIconView();
      $item->setName($user->getUsername());
      $item->addClass('main-menu-item-icon-profile '.$class);
      $item->addStyle('background-image: url('.$image.')');
      $item->setHref('/p/'.$user->getUsername().'/');
      $item->setSortOrder(0.0);
      $items[] = $item;
    }

    return $items;
  }

}
