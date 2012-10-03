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

final class PhabricatorApplicationUIExamples extends PhabricatorApplication {

  public function getBaseURI() {
    return '/uiexample/';
  }

  public function getShortDescription() {
    return 'Developer UI Examples';
  }

  public function getAutospriteName() {
    return 'uiexample';
  }

  public function getTitleGlyph() {
    return "\xE2\x8F\x9A";
  }

  public function getFlavorText() {
    return pht('A gallery of modern art.');
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getRoutes() {
    return array(
      '/uiexample/' => array(
        '' => 'PhabricatorUIExampleRenderController',
        'view/(?P<class>[^/]+)/' => 'PhabricatorUIExampleRenderController',
      ),
    );
  }

}
