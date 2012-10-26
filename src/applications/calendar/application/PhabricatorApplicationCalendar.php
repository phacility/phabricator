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

final class PhabricatorApplicationCalendar extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Dates and Stuff');
  }

  public function getFlavorText() {
    return pht('Never miss an episode ever again.');
  }

  public function getBaseURI() {
    return '/calendar/';
  }

  public function getTitleGlyph() {
    // Unicode has a calendar character but it's in some distant code plane,
    // use "keyboard" since it looks vaguely similar.
    return "\xE2\x8C\xA8";
  }

  public function getRoutes() {
    return array(
      '/calendar/' => array(
        '' => 'PhabricatorCalendarBrowseController',
        'status/' => array(
          '' => 'PhabricatorCalendarViewStatusController',
          'create/' =>
            'PhabricatorCalendarEditStatusController',
          'delete/(?P<id>[1-9]\d*)/' =>
            'PhabricatorCalendarDeleteStatusController',
          'edit/(?P<id>[1-9]\d*)/' =>
            'PhabricatorCalendarEditStatusController',
          'view/(?P<phid>[^/]+)/' =>
            'PhabricatorCalendarViewStatusController',
        ),
      ),
    );
  }

}
