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
final class PhamePostViewController
extends PhameController {

  private $postPHID;
  private $phameTitle;
  private $bloggerName;

  private function setPostPHID($post_phid) {
    $this->postPHID = $post_phid;
    return $this;
  }
  private function getPostPHID() {
    return $this->postPHID;
  }
  private function setPhameTitle($phame_title) {
    $this->phameTitle = $phame_title;
    return $this;
  }
  private function getPhameTitle() {
    return $this->phameTitle;
  }
  private function setBloggerName($blogger_name) {
    $this->bloggerName = $blogger_name;
    return $this;
  }
  private function getBloggerName() {
    return $this->bloggerName;
  }

  protected function getSideNavFilter() {
    $filter = 'post/view/'.$this->getPostPHID();
    return $filter;
  }
  protected function getSideNavExtraPostFilters() {
      $filters =  array(
        array('key'  => $this->getSideNavFilter(),
              'name' => $this->getPhameTitle())
      );
      return $filters;
  }

  public function willProcessRequest(array $data) {
    $this->setPostPHID(idx($data, 'phid'));
    $this->setPhameTitle(idx($data, 'phametitle'));
    $this->setBloggerName(idx($data, 'bloggername'));
  }

  public function processRequest() {
    $request   = $this->getRequest();
    $user      = $request->getUser();
    $post_phid = null;

    if ($this->getPostPHID()) {
      $post_phid = $this->getPostPHID();
      if (!$post_phid) {
        return new Aphront404Response();
      }

      $post = id(new PhamePost())->loadOneWhere(
        'phid = %s',
        $post_phid);

      if ($post) {
        $this->setPhameTitle($post->getPhameTitle());
      }

      $blogger = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s', $post->getBloggerPHID());
      if (!$blogger) {
        return new Aphront404Response();
      }

    } else if ($this->getBloggerName() && $this->getPhameTitle()) {
      $phame_title = $this->getPhameTitle();
      $phame_title = PhabricatorSlug::normalize($phame_title);
      if ($phame_title != $this->getPhameTitle()) {
        $uri = $post->getViewURI($this->getBloggerName());
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
      $blogger = id(new PhabricatorUser())->loadOneWhere(
        'username = %s',
        $this->getBloggerName());
      if (!$blogger) {
        return new Aphront404Response();
      }
      $post = id(new PhamePost())->loadOneWhere(
        'bloggerPHID = %s AND phameTitle = %s',
        $blogger->getPHID(),
        $this->getPhameTitle());
    }

    if (!$post) {
      return new Aphront404Response();
    }

    if ($post->isDraft() &&
        $post->getBloggerPHID() != $user->getPHID()) {
      return new Aphront404Response();
    }

    if ($post->isDraft()) {
      $notice = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle('You are previewing a draft.')
        ->setErrors(array(
          'Only you can see this draft until you publish it.',
          'If you chose a comment widget it will show up when you publish.',
        ));
    } else {
      $notice = null;
    }

    $page = id(new PhamePostDetailView())
      ->setUser($user)
      ->setRequestURI($request->getRequestURI())
      ->setBlogger($blogger)
      ->setPost($post);

    $this->setShowSideNav(false);
    return $this->buildStandardPageResponse(
      array(
        $notice,
        $page,
      ),
      array(
        'title'   => $post->getTitle(),
      ));
  }
}
