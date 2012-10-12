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
final class PhamePostListView extends AphrontView {

  private $user;
  private $posts;
  private $bloggers;
  private $actions = array();
  private $draftList;
  private $requestURI;
  private $showPostComments;

  public function setShowPostComments($show_post_comments) {
    $this->showPostComments = $show_post_comments;
    return $this;
  }
  private function getShowPostComments() {
    return $this->showPostComments;
  }

  public function setRequestURI($request_uri) {
    $this->requestURI = $request_uri;
    return $this;
  }
  public function getRequestURI() {
    return $this->requestURI;
  }

  public function setDraftList($draft_list) {
    $this->draftList = $draft_list;
    return $this;
  }
  public function isDraftList() {
    return (bool) $this->draftList;
  }
  private function getPostNoun() {
    if ($this->isDraftList()) {
      $noun = 'Draft';
    } else {
      $noun = 'Post';
    }
    return $noun;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  private function getUser() {
    return $this->user;
  }
  public function setPosts(array $posts) {
    assert_instances_of($posts, 'PhamePost');
    $this->posts = $posts;
    return $this;
  }
  private function getPosts() {
    return $this->posts;
  }
  public function setBloggers(array $bloggers) {
    assert_instances_of($bloggers, 'PhabricatorObjectHandle');
    $this->bloggers = $bloggers;
    return $this;
  }
  private function getBloggers() {
    return $this->bloggers;
  }
  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }
  private function getActions() {
    return $this->actions;
  }

  public function render() {
    $user      = $this->getUser();
    $posts     = $this->getPosts();
    $bloggers  = $this->getBloggers();
    $noun      = $this->getPostNoun();

    if (empty($posts)) {
      $panel = id(new AphrontPanelView())
        ->setHeader(sprintf('No %ss... Yet!', $noun))
        ->setCaption('Will you answer the call to phame?')
        ->setCreateButton(sprintf('New %s', $noun),
                          sprintf('/phame/%s/new', strtolower($noun)));
      return $panel->render();
    }

    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('phame-css');

    $engine  = PhabricatorMarkupEngine::newPhameMarkupEngine();
    $html    = array();
    $actions = $this->getActions();
    foreach ($posts as $post) {
      $blogger_phid = $post->getBloggerPHID();
      $blogger      = $bloggers[$blogger_phid];
      $detail = id(new PhamePostDetailView())
        ->setUser($user)
        ->setPost($post)
        ->setBlogger($blogger)
        ->setActions($actions)
        ->setRequestURI($this->getRequestURI())
        ->setShowComments($this->getShowPostComments());

      $html[] = $detail->render();
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'blog-post-list'
      ),
      implode('', $html)
    );
  }
}
