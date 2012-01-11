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
    $items = array(
      'resourcelist' => array(
        'href' => '/drydock/resource/',
        'name' =>  'Resources',
      ),
      'leaselist' => array(
        'href' => '/drydock/lease/',
        'name' => 'Leases',
      ),
    );

    $nav = new AphrontSideNavView();
    foreach ($items as $key => $info) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => $info['href'],
            'class' => ($key == $selected ? 'aphront-side-nav-selected' : null),
          ),
          phutil_escape_html($info['name'])));
    }

    return $nav;
  }

}
