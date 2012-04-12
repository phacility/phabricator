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

/**
 * @group phame
 */
abstract class PhameController extends PhabricatorController {
  private $showSideNav;

  protected function setShowSideNav($value) {
    $this->showSideNav = (bool) $value;
    return $this;
  }
  private function showSideNav() {
    return $this->showSideNav;
  }

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Phame');
    $page->setBaseURI('/phame/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xe2\x9c\xa9");

    $tabs = array(
      'help' => array(
        'name' => 'Help',
        'href' =>
          PhabricatorEnv::getDoclink('article/Phame_User_Guide.html'),
      ),
    );
    $page->setTabs($tabs, idx($data, 'tab'));
    if ($this->showSideNav()) {
      $nav = $this->renderSideNavFilterView($this->getSideNavFilter());
      $nav->appendChild($view);
      $page->appendChild($nav);
    } else {
      $page->appendChild($view);
    }

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  private function renderSideNavFilterView($filter) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/phame/'));
    $nav->addLabel('Drafts');
    $nav->addFilter('post/new',
                    'New Draft');
    $nav->addFilter('draft',
                    'My Drafts');
    $nav->addSpacer();
    $nav->addLabel('Posts');
    $nav->addFilter('post',
                    'My Posts');
    foreach ($this->getSideNavExtraPostFilters() as $post_filter) {
      $nav->addFilter($post_filter['key'],
                      $post_filter['name']);
    }

    $nav->selectFilter($filter, 'post');

    return $nav;
  }

  protected function getSideNavExtraPostFilters() {
    return array();
  }
  protected function getSideNavFilter() {
    return 'post';
  }

}
