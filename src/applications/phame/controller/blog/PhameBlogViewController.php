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
final class PhameBlogViewController extends PhameController {

  private $blogPHID;

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
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withPHIDs(array($this->getBlogPHID()))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $posts = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->execute();

    $skin = $blog->getSkinRenderer();
    $skin
      ->setUser($this->getRequest()->getUser())
      ->setBloggers($this->loadViewerHandles(mpull($posts, 'getBloggerPHID')))
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
}
