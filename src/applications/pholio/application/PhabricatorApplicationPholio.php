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

/**
 * @group pholio
 */
final class PhabricatorApplicationPholio extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    // TODO: See getApplicationGroup().
    return false;
  }

  public function getBaseURI() {
    return '/pholio/';
  }

  public function getShortDescription() {
    return 'Design Review';
  }

  public function getAutospriteName() {
    return 'pholio';
  }

  public function getTitleGlyph() {
    return "\xE2\x9D\xA6";
  }

  public function getFlavorText() {
    return pht('Things before they were cool.');
  }

  public function getApplicationGroup() {
    // TODO: Move to CORE, this just keeps it out of the side menu.
    return self::GROUP_COMMUNICATION;
  }

  public function getRoutes() {
    return array(
      '/M(?P<id>[1-9]\d*)' => 'PholioMockViewController',
      '/pholio/' => array(
        '' => 'PholioMockListController',
        'view/(?P<view>\w+)/' => 'PholioMockListController',
        'new/'                => 'PholioMockEditController',
        'edit/(?P<id>\d+)/'   => 'PholioMockEditController',
      ),
    );
  }

}
