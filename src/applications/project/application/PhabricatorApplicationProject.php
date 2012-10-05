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

final class PhabricatorApplicationProject extends PhabricatorApplication {

  public function getName() {
    return 'Projects';
  }

  public function getShortDescription() {
    return 'Organize Work';
  }

  public function getBaseURI() {
    return '/project/';
  }

  public function getAutospriteName() {
    return 'projects';
  }

  public function getFlavorText() {
    return pht('Group stuff into big piles.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRoutes() {
    return array(
      '/project/' => array(
        '' => 'PhabricatorProjectListController',
        'filter/(?P<filter>[^/]+)/' => 'PhabricatorProjectListController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectProfileEditController',
        'members/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectMembersEditController',
        'view/(?P<id>[1-9]\d*)/(?:(?P<page>\w+)/)?'
          => 'PhabricatorProjectProfileController',
        'create/' => 'PhabricatorProjectCreateController',
        'update/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorProjectUpdateController',
      ),
    );
  }

}
