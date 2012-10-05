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

final class PhabricatorApplicationSlowvote extends PhabricatorApplication {

  public function getBaseURI() {
    return '/vote/';
  }

  public function getAutospriteName() {
    return 'slowvote';
  }

  public function getShortDescription() {
    return 'Conduct Polls';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x94";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Slowvote_User_Guide.html');
  }

  public function getFlavorText() {
    return pht('Design by committee.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/V(?P<id>[1-9]\d*)' => 'PhabricatorSlowvotePollController',
      '/vote/' => array(
        '(?:view/(?P<view>\w+)/)?' => 'PhabricatorSlowvoteListController',
        'create/' => 'PhabricatorSlowvoteCreateController',
      ),
    );
  }

}
