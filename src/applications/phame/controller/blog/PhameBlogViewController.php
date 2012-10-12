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
final class PhameBlogViewController
  extends PhameController {

  private $blogPHID;
  private $bloggerPHIDs;
  private $postPHIDs;

  private function setPostPHIDs($post_phids) {
    $this->postPHIDs = $post_phids;
    return $this;
  }
  private function getPostPHIDs() {
    return $this->postPHIDs;
  }

  private function setBloggerPHIDs($blogger_phids) {
    $this->bloggerPHIDs = $blogger_phids;
    return $this;
  }
  private function getBloggerPHIDs() {
    return $this->bloggerPHIDs;
  }

  private function setBlogPHID($blog_phid) {
    $this->blogPHID = $blog_phid;
    return $this;
  }
  private function getBlogPHID() {
    return $this->blogPHID;
  }

  protected function getSideNavFilter() {
    $filter = 'blog/view/'.$this->getBlogPHID();
    return $filter;
  }
  protected function getSideNavExtraBlogFilters() {
      $filters =  array(
        array('key'  => $this->getSideNavFilter(),
              'name' => $this->getPhameTitle())
      );
      return $filters;
  }

  public function willProcessRequest(array $data) {
    $this->setBlogPHID(idx($data, 'phid'));
  }

  public function processRequest() {
    $request   = $this->getRequest();
    $user      = $request->getUser();
    $blog_phid = $this->getBlogPHID();

    $blogs = id(new PhameBlogQuery())
      ->withPHIDs(array($blog_phid))
      ->execute();
    $blog = reset($blogs);

    if (!$blog) {
      return new Aphront404Response();
    }

    $this->loadEdges();

    $blogger_phids = $this->getBloggerPHIDs();
    if ($blogger_phids) {
      $bloggers = $this->loadViewerHandles($blogger_phids);
    } else {
      $bloggers = array();
    }

    $post_phids = $this->getPostPHIDs();
    if ($post_phids) {
      $posts = id(new PhamePostQuery())
        ->withPHIDs($post_phids)
        ->withVisibility(PhamePost::VISIBILITY_PUBLISHED)
        ->execute();
    } else {
      $posts = array();
    }

    $notice = array();
    if ($request->getExists('new')) {
      $notice =
        array(
          'title' => 'Successfully created your blog.',
          'body'  => 'Time to write some posts.'
        );
    } else if ($request->getExists('edit')) {
      $notice =
        array(
          'title' => 'Successfully edited your blog.',
          'body'  => 'Time to write some posts.'
        );
    }

    $skin = $blog->getSkinRenderer();
    $skin
      ->setUser($this->getRequest()->getUser())
      ->setNotice($notice)
      ->setBloggers($bloggers)
      ->setPosts($posts)
      ->setBlog($blog)
      ->setRequestURI($this->getRequest()->getRequestURI());

    $this->setShowSideNav(false);
    $this->setShowChrome($skin->getShowChrome());
    $this->setDeviceReady($skin->getDeviceReady());
    $skin->setIsExternalDomain($blog->getDomain() == $request->getHost());

    return $this->buildStandardPageResponse(
      array(
        $skin
      ),
      array(
        'title' => $blog->getName(),
      ));
  }

  private function loadEdges() {

    $edge_types = array(
      PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER,
      PhabricatorEdgeConfig::TYPE_BLOG_HAS_POST,
    );
    $blog_phid = $this->getBlogPHID();
    $phids = array($blog_phid);

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($phids)
      ->withEdgeTypes($edge_types)
      ->execute();

    $blogger_phids = array_keys(
      $edges[$blog_phid][PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER]
    );
    $this->setBloggerPHIDs($blogger_phids);

    $post_phids = array_keys(
      $edges[$blog_phid][PhabricatorEdgeConfig::TYPE_BLOG_HAS_POST]
    );
    $this->setPostPHIDs($post_phids);

  }
}
