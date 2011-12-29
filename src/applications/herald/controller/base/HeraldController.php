<?php

/*
 * Copyright 2011 Facebook, Inc.
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

abstract class HeraldController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Herald');
    $page->setBaseURI('/herald/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\xBF");

    $doclink = PhabricatorEnv::getDoclink('article/Herald_User_Guide.html');

    $nav = new AphrontSideNavFilterView();
    $nav
      ->setBaseURI(new PhutilURI('/herald/'))
      ->addLabel('Rules')
      ->addFilter('new', 'Create Rule');
    $rules_map = HeraldContentTypeConfig::getContentTypeMap();
    $first_filter = null;
    foreach ($rules_map as $key => $value) {
      $nav->addFilter('view/'.$key, $value);
      if (!$first_filter) {
        $first_filter = 'view/'.$key;
      }
    }

    $nav
      ->addSpacer()
      ->addLabel('Utilities')
      ->addFilter('test',       'Test Console')
      ->addFilter('transcript', 'Transcripts');

    $user = $this->getRequest()->getUser();
    if ($user->getIsAdmin()) {
      $nav
        ->addSpacer()
        ->addLabel('Admin');
      $view_PHID = nonempty($this->getRequest()->getStr('phid'), null);
      foreach ($rules_map as $key => $value) {
        $nav
          ->addFilter('all/view/'.$key, $value);
      }
    }

    $nav->selectFilter($this->getFilter(), $first_filter);
    $nav->appendChild($view);
    $page->appendChild($nav);

    $tabs = array(
      'help' => array(
        'href' => $doclink,
        'name' => 'Help',
      ),
    );
    $page->setTabs($tabs, null);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }

  abstract function getFilter();
}
