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

  private $bloggerName;
  private $isDraft;

  private function setBloggerName($blogger_name) {
    $this->bloggerName = $blogger_name;
    return $this;
  }
  private function getBloggerName() {
    return $this->bloggerName;
  }

  protected function getSideNavExtraPostFilters() {
    if ($this->isDraft() || !$this->getBloggerName()) {
      return array();
    }

    return
        array(array('key'  => $this->getSideNavFilter(),
                    'name' => 'Posts by '.$this->getBloggerName()));
  }

  protected function getSideNavFilter() {
    if ($this->getBloggerName()) {
      $filter = 'posts/'.$this->getBloggerName();
    } else if ($this->isDraft()) {
      $filter = 'draft';
    } else {
      $filter = 'posts';
    }
    return $filter;
  }

  private function isDraft() {
    return (bool) $this->isDraft;
  }
  protected function setIsDraft($is_draft) {
    $this->isDraft = $is_draft;
    return $this;
  }

  public function willProcessRequest(array $data) {
    $this->setBloggerName(idx($data, 'bloggername'));
  }

  public function processRequest() {
    $request   = $this->getRequest();
    $user      = $request->getUser();
    $pager     = new AphrontPagerView();
    $page_size = 50;
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setPageSize($page_size);
    $pager->setOffset($request->getInt('offset'));

    if ($this->getBloggerName()) {
      $blogger = id(new PhabricatorUser())->loadOneWhere(
        'username = %s',
        $this->getBloggerName());
      if (!$blogger) {
        return new Aphront404Response();
      }
      $page_title = 'Posts by '.$this->getBloggerName();
      if ($blogger->getPHID() == $user->getPHID()) {
        $actions    = array('view', 'edit');
      } else {
        $actions    = array('view');
      }
      $this->setShowSideNav(false);
    } else {
      $blogger    = $user;
      $page_title = 'Posts by '.$user->getUserName();
      $actions    = array('view', 'edit');
      $this->setShowSideNav(true);
    }
    $phid = $blogger->getPHID();
    // user gets to see their own unpublished stuff
    if ($phid == $user->getPHID() && $this->isDraft()) {
      $post_visibility = PhamePost::VISIBILITY_DRAFT;
    } else {
      $post_visibility = PhamePost::VISIBILITY_PUBLISHED;
    }
    $query    = new PhamePostQuery();
    $query->withBloggerPHID($phid);
    $query->withVisibility($post_visibility);
    $posts    = $query->executeWithPager($pager);
    $bloggers = array($blogger->getPHID() => $blogger);

    $panel = id(new PhamePostListView())
      ->setUser($user)
      ->setBloggers($bloggers)
      ->setPosts($posts)
      ->setActions($actions)
      ->setDraftList($this->isDraft());

    return $this->buildStandardPageResponse(
      array(
        $panel,
        $pager
      ),
      array(
        'title'   => $page_title,
      ));
  }
}
