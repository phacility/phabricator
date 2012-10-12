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
  private $showChrome = true;
  private $deviceReady = false;

  protected function setShowSideNav($value) {
    $this->showSideNav = (bool) $value;
    return $this;
  }
  private function showSideNav() {
    return $this->showSideNav;
  }

  protected function setShowChrome($show_chrome) {
    $this->showChrome = $show_chrome;
    return $this;
  }
  private function getShowChrome() {
    return $this->showChrome;
  }

  public function setDeviceReady($device_ready) {
    $this->deviceReady = $device_ready;
    return $this;
  }
  public function getDeviceReady() {
    return $this->deviceReady;
  }

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Phame');
    $page->setBaseURI('/phame/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xe2\x9c\xa9");
    $page->setShowChrome($this->getShowChrome());
    $page->setDeviceReady($this->getDeviceReady());

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
    $base_uri = new PhutilURI('/phame/');
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI($base_uri);
    $nav->addLabel('Drafts');
    $nav->addFilter('post/new',
                    'New Draft');
    $nav->addFilter('draft',
                    'My Drafts');
    foreach ($this->getSideNavExtraDraftFilters() as $draft_filter) {
      $nav->addFilter($draft_filter['key'],
                      $draft_filter['name'],
                      idx($draft_filter, 'uri'));
    }

    $nav->addSpacer();
    $nav->addLabel('Posts');
    $nav->addFilter('post',
                    'My Posts');
    $nav->addFilter('post/all',
                    'All Posts',
                    $base_uri);
    foreach ($this->getSideNavExtraPostFilters() as $post_filter) {
      $nav->addFilter($post_filter['key'],
                      $post_filter['name'],
                      idx($post_filter, 'uri'));
    }

    $nav->addSpacer();
    $nav->addLabel('Blogs');
    foreach ($this->getSideNavBlogFilters() as $blog_filter) {
      $nav->addFilter($blog_filter['key'],
                      $blog_filter['name'],
                      idx($blog_filter, 'uri'));
    }

    $nav->selectFilter($filter);

    return $nav;
  }

  protected function getSideNavExtraDraftFilters() {
    return array();
  }

  protected function getSideNavExtraPostFilters() {
    return array();
  }

  protected function getSideNavBlogFilters() {
    return array(
      array(
        'key'  => 'blog',
        'name' => 'My Blogs',
      ),
      array(
        'key'  => 'blog/all',
        'name' => 'All Blogs',
      ),
    );
  }

  protected function getSideNavFilter() {
    return 'post';
  }

  protected function getPager() {
    $request   = $this->getRequest();
    $pager     = new AphrontPagerView();
    $page_size = 50;
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setPageSize($page_size);
    $pager->setOffset($request->getInt('offset'));

    return $pager;
  }

  protected function buildNoticeView() {
    $notice_view = id(new AphrontErrorView())
      ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
      ->setTitle('Meta thoughts and feelings');
    return $notice_view;
  }
}
