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
final class PhameBlogLiveController extends PhameController {

  private $id;
  private $more;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->more = idx($data, 'more', '');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    if ($blog->getDomain() && ($request->getHost() != $blog->getDomain())) {
      return id(new AphrontRedirectResponse())
        ->setURI('http://'.$blog->getDomain().'/'.$this->more);
    }

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $query = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBlogPHIDs(array($blog->getPHID()));

    $matches = null;
    $path = $this->more;
    if (preg_match('@^/(?P<view>[^/]+)/(?P<name>.*)$@', $path, $matches)) {
      $view = $matches['view'];
      $name = $matches['name'];
    } else {
      $view = '';
      $name = '';
    }

    switch ($view) {
      case 'post':
        $query->withPhameTitles(array($name));
        break;
    }

    $posts = $query->executeWithCursorPager($pager);

    $skin = $blog->getSkinRenderer();
    $skin
      ->setUser($user)
      ->setPosts($posts)
      ->setBloggers($this->loadViewerHandles(mpull($posts, 'getBloggerPHID')))
      ->setBlog($blog)
      ->setRequestURI($this->getRequest()->getRequestURI());

    $page = $this->buildStandardPageView();
    $page->appendChild($skin);
    $page->setShowChrome(false);

    $response = new AphrontWebpageResponse();
    $response->setContent($page->render());
    return $response;
  }

}
