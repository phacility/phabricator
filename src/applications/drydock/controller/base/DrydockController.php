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

abstract class DrydockController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Drydock');
    $page->setBaseURI('/drydock/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\x82");

    $page->appendChild($view);

    $help_uri = PhabricatorEnv::getDoclink('article/Drydock_User_Guide.html');
    $page->setTabs(
      array(
        'help' => array(
          'name' => 'Help',
          'href'  => $help_uri,
        ),
      ), null);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  final protected function buildSideNav($selected) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/drydock/'));
    $nav->addFilter('resource', 'Resources');
    $nav->addFilter('lease',    'Leases');
    $nav->addSpacer();
    $nav->addFilter('log',      'Logs');

    $nav->selectFilter($selected, 'resource');

    return $nav;
  }

}
