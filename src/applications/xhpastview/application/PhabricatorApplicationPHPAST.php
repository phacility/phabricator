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

final class PhabricatorApplicationPHPAST extends PhabricatorApplication {

  public function getBaseURI() {
    return '/xhpast/';
  }

  public function getAutospriteName() {
    return 'phpast';
  }

  public function getShortDescription() {
    return 'Visual PHP Parser';
  }

  public function getTitleGlyph() {
    return "\xE2\x96\xA0";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getRoutes() {
    return array(
      '/xhpast/' => array(
        '' => 'PhabricatorXHPASTViewRunController',
        'view/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewFrameController',
        'frameset/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewFramesetController',
        'input/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewInputController',
        'tree/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewTreeController',
        'stream/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewStreamController',
      ),
    );
  }

}
