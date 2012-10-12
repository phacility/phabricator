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
abstract class PhamePostListBaseController
  extends PhameController {

  private $phamePostQuery;
  private $actions;
  private $pageTitle;

  protected function setPageTitle($page_title) {
    $this->pageTitle = $page_title;
    return $this;
  }
  private function getPageTitle() {
    return $this->pageTitle;
  }

  protected function setActions($actions) {
    $this->actions = $actions;
    return $this;
  }
  private function getActions() {
    return $this->actions;
  }

  protected function setPhamePostQuery(PhamePostQuery $query) {
    $this->phamePostQuery = $query;
    return $this;
  }
  private function getPhamePostQuery() {
    return $this->phamePostQuery;
  }

  protected function isDraft() {
    return false;
  }

  protected function getNoticeView() {
    return null;
  }

  private function loadBloggersFromPosts(array $posts) {
    assert_instances_of($posts, 'PhamePost');
    if (empty($posts)) {
      return array();
    }

    $blogger_phids = mpull($posts, 'getBloggerPHID', 'getBloggerPHID');

    return
      $this->loadViewerHandles($blogger_phids);
  }

  protected function buildPostListPageResponse() {
    $request = $this->getRequest();
    $pager   = $this->getPager();
    $query   = $this->getPhamePostQuery();
    $posts   = $query->executeWithOffsetPager($pager);

    $bloggers =  $this->loadBloggersFromPosts($posts);

    $panel = id(new PhamePostListView())
      ->setUser($this->getRequest()->getUser())
      ->setBloggers($bloggers)
      ->setPosts($posts)
      ->setActions($this->getActions())
      ->setRequestURI($request->getRequestURI())
      ->setDraftList($this->isDraft());

    return $this->buildStandardPageResponse(
      array(
        $this->getNoticeView(),
        $panel,
        $pager
      ),
      array(
        'title' => $this->getPageTitle(),
      ));
  }
}
