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
final class PhameBlogListController extends PhameController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->renderSideNavFilterView(null);
    $filter = $nav->selectFilter('blog/'.$this->filter, 'blog/user');

    $query = id(new PhameBlogQuery())
      ->setViewer($user);

    switch ($filter) {
      case 'blog/all':
        $title = pht('All Blogs');
        $nodata = pht('No blogs have been created.');
        break;
      case 'blog/user':
        $title = pht('Joinable Blogs');
        $nodata = pht('There are no blogs you can contribute to.');
        $query->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_JOIN,
          ));
        break;
      default:
        throw new Exception("Unknown filter '{$filter}'!");
    }

    $pager = id(new AphrontPagerView())
      ->setURI($request->getRequestURI(), 'offset')
      ->setOffset($request->getInt('offset'));

    $blogs = $query->executeWithOffsetPager($pager);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $blog_list = $this->renderBlogList($blogs, $user, $nodata);
    $blog_list->setPager($pager);

    $nav->appendChild(
      array(
        $header,
        $blog_list,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $title,
        'device'  => true,
      ));
  }

  private function renderBlogList(
    array $blogs,
    PhabricatorUser $user,
    $nodata) {

    $view = new PhabricatorObjectItemListView();
    $view->setNoDataString($nodata);
    foreach ($blogs as $blog) {

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($blog->getName())
        ->setHref($this->getApplicationURI('blog/view/'.$blog->getID().'/'))
        ->addDetail(
          pht('Custom Domain'),
          phutil_escape_html($blog->getDomain()));

      $view->addItem($item);
    }

    return $view;
  }

}
