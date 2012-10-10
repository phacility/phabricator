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

final class PhabricatorApplicationPaste extends PhabricatorApplication {

  public function getBaseURI() {
    return '/paste/';
  }

  public function getAutospriteName() {
    return 'paste';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x8E";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/P(?P<id>[1-9]\d*)' => 'PhabricatorPasteViewController',
      '/paste/' => array(
        '' => 'PhabricatorPasteEditController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorPasteEditController',
        'filter/(?P<filter>\w+)/' => 'PhabricatorPasteListController',
      ),
    );
  }

}
