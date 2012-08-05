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

final class PhabricatorApplicationSettings extends PhabricatorApplication {

  public function getBaseURI() {
    return '/settings/';
  }

  public function getShortDescription() {
    return 'User Preferences';
  }

  public function getIconURI() {
    return celerity_get_resource_uri('/rsrc/image/app/app_settings.png');
  }

  public function getRoutes() {
    return array(
      '/settings/' => array(
        '(?:page/(?P<page>[^/]+)/)?' => 'PhabricatorUserSettingsController',
      ),
    );
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller) {

    $items = array();

    if ($user->isLoggedIn()) {
      require_celerity_resource('phabricator-glyph-css');

      $item = new PhabricatorMainMenuIconView();
      $item->setName(pht('Settings'));
      $item->addClass('glyph glyph-settings');
      $item->setHref('/settings/');
      $item->setSortOrder(0.90);
      $items[] = $item;
    }

    return $items;
  }

}
