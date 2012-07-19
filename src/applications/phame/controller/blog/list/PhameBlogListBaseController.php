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
abstract class PhameBlogListBaseController
  extends PhameController {

  private $blogs;
  private $pageTitle;

  protected function setBlogs(array $blogs) {
    assert_instances_of($blogs, 'PhameBlog');
    $this->blogs = $blogs;
    return $this;
  }
  private function getBlogs() {
    return $this->blogs;
  }

  protected function setPageTitle($page_title) {
    $this->pageTitle = $page_title;
    return $this;
  }
  private function getPageTitle() {
    return $this->pageTitle;
  }

  protected function getNoticeView() {
    return null;
  }

  private function getBlogListPanel() {
    $blogs = $this->getBlogs();

    $panel = id(new PhameBlogListView())
      ->setUser($this->getRequest()->getUser())
      ->setBlogs($blogs)
      ->setHeader($this->getPageTitle());

    return $panel;
  }

  protected function buildBlogListPageResponse() {
    return $this->buildStandardPageResponse(
      array(
        $this->getNoticeView(),
        $this->getBlogListPanel(),
        $this->getPager(),
      ),
      array(
        'title'   => $this->getPageTitle(),
      ));
  }
}
