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

final class PhabricatorApplicationPhriction extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Wiki';
  }

  public function getBaseURI() {
    return '/w/';
  }

  public function getAutospriteName() {
    return 'phriction';
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Phriction_User_Guide.html');
  }

  public function getRoutes() {
    return array(
      // Match "/w/" with slug "/".
      '/w(?P<slug>/)'    => 'PhrictionDocumentController',
      // Match "/w/x/y/z/" with slug "x/y/z/".
      '/w/(?P<slug>.+/)' => 'PhrictionDocumentController',

      '/phriction/' => array(
        ''                       => 'PhrictionListController',
        'list/(?P<view>[^/]+)/'  => 'PhrictionListController',

        'history(?P<slug>/)'     => 'PhrictionHistoryController',
        'history/(?P<slug>.+/)'  => 'PhrictionHistoryController',

        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'PhrictionEditController',
        'delete/(?P<id>[1-9]\d*)/'    => 'PhrictionDeleteController',

        'preview/' => 'PhrictionDocumentPreviewController',
        'diff/(?P<id>[1-9]\d*)/' => 'PhrictionDiffController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getApplicationOrder() {
    return 0.140;
  }

}

