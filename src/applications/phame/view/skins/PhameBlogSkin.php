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
abstract class PhameBlogSkin extends AphrontView {

  private $user;
  private $blog;
  private $bloggers;
  private $posts;
  private $actions;
  private $notice;
  private $showChrome;
  private $deviceReady;
  private $isExternalDomain;
  private $requestURI;
  private $showPostComments;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  public function getUser() {
    return $this->user;
  }

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }
  public function getBlog() {
    return $this->blog;
  }

  public function setBloggers(array $bloggers) {
    assert_instances_of($bloggers, 'PhabricatorObjectHandle');
    $this->bloggers = $bloggers;
    return $this;
  }
  public function getBloggers() {
    return $this->bloggers;
  }

  public function setPosts(array $posts) {
    assert_instances_of($posts, 'PhamePost');
    $this->posts = $posts;
    return $this;
  }
  protected function getPosts() {
    return $this->posts;
  }

  public function setActions($actions) {
    $this->actions = $actions;
    return $this;
  }
  public function getActions() {
    return $this->actions;
  }

  public function setNotice(array $notice) {
    $this->notice = $notice;
    return $this;
  }
  protected function getNotice() {
    return $this->notice;
  }

  public function setIsExternalDomain($is_external_domain) {
    $this->isExternalDomain = $is_external_domain;
    return $this;
  }
  protected function getIsExternalDomain() {
    return $this->isExternalDomain;
  }

  public function setRequestURI($request_uri) {
    $this->requestURI = $request_uri;
    return $this;
  }
  private function getRequestURI() {
    return $this->requestURI;
  }

  protected function setShowChrome($show_chrome) {
    $this->showChrome = $show_chrome;
    return $this;
  }
  public function getShowChrome() {
    return $this->showChrome;
  }

  protected function setDeviceReady($device_ready) {
    $this->deviceReady = $device_ready;
    return $this;
  }
  public function getDeviceReady() {
    return $this->deviceReady;
  }

  public function setShowPostComments($show_post_comments) {
    $this->showPostComments = $show_post_comments;
    return $this;
  }
  public function getShowPostComments() {
    return $this->showPostComments;
  }


  public function __construct() {
    // set any options here
  }

  public function render() {
    return
      $this->renderHeader().
      $this->renderBody().
      $this->renderFooter();
  }

  abstract protected function renderHeader();
  abstract protected function renderBody();
  abstract protected function renderFooter();

  final public function renderPosts() {
    $list = id(new PhamePostListView())
      ->setUser($this->getUser())
      ->setPosts($this->getPosts())
      ->setBloggers($this->getBloggers())
      ->setRequestURI($this->getRequestURI())
      ->setShowPostComments($this->getShowPostComments())
      ->setDraftList(false);

    return $list->render();
  }

  final protected function renderBlogDetails() {
    $details = array();

    $blog = $this->getBlog();
    if ($blog) {
      $detail = id(new PhameBlogDetailView())
        ->setUser($this->getUser())
        ->setBlog($this->getBlog())
        ->setBloggers($this->getBloggers());
      $details[] = $detail->render();
    }

    // TODO - more cool boxes for the right hand column...!

    return implode('', $details);
  }

  final protected function renderNotice() {
    $notice_html = '';
    if ($this->getIsExternalDomain()) {
      return $notice_html;
    }

    $notice = $this->getNotice();
    if ($notice) {
      $header  = $notice['title'];
      $details = $notice['body'];
      $notice_html  = phutil_render_tag(
        'div',
        array(
          'class' => 'notice'
        ),
        phutil_render_tag(
          'h3',
          array(),
          $header
        ).phutil_render_tag(
          'h4',
          array(),
          $details
        )
      );
    }
    return $notice_html;
  }

}
