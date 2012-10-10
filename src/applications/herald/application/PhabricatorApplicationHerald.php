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

final class PhabricatorApplicationHerald extends PhabricatorApplication {

  public function getBaseURI() {
    return '/herald/';
  }

  public function getAutospriteName() {
    return 'herald';
  }

  public function getShortDescription() {
    return 'Create Notification Rules';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBF";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Herald_User_Guide.html');
  }

  public function getFlavorText() {
    return pht('Watch for danger!');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRoutes() {
    return array(
      '/herald/' => array(
        '' => 'HeraldHomeController',
        'view/(?P<content_type>[^/]+)/(?:(?P<rule_type>[^/]+)/)?'
          => 'HeraldHomeController',
        'new/(?:(?P<type>[^/]+)/(?:(?P<rule_type>[^/]+)/)?)?'
          => 'HeraldNewController',
        'rule/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleController',
        'history/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleEditHistoryController',
        'delete/(?P<id>[1-9]\d*)/' => 'HeraldDeleteController',
        'test/' => 'HeraldTestConsoleController',
        'transcript/' => 'HeraldTranscriptListController',
        'transcript/(?P<id>[1-9]\d*)/(?:(?P<filter>\w+)/)?'
          => 'HeraldTranscriptController',
      ),
    );
  }

}
