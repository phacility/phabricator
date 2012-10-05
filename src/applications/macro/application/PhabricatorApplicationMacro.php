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

final class PhabricatorApplicationMacro extends PhabricatorApplication {

  public function getBaseURI() {
    return '/macro/';
  }

  public function getShortDescription() {
    return 'Image Macros and Memes';
  }

  public function getAutospriteName() {
    return 'macro';
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\x98";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/macro/' => array(
        '' => 'PhabricatorMacroListController',
        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'PhabricatorMacroEditController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroDeleteController',
      ),
    );
  }

}
