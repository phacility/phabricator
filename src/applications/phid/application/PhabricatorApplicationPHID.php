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

final class PhabricatorApplicationPHID extends PhabricatorApplication {

  public function getName() {
    return 'PHID Manager';
  }

  public function getBaseURI() {
    return '/phid/';
  }

  public function getAutospriteName() {
    return 'phid';
  }

  public function getShortDescription() {
    return 'Lookup PHIDs';
  }

  public function getTitleGlyph() {
    return "#";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getRoutes() {
    return array(
      '/phid/' => array(
        '' => 'PhabricatorPHIDLookupController',
      ),
    );
  }

}
