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
final class PhameUserBlogListController
  extends PhameBlogListBaseController {

  protected function getNoticeView() {
    $request = $this->getRequest();

    if ($request->getExists('deleted')) {
      $notice_view = $this->buildNoticeView()
        ->appendChild('Successfully deleted blog.');
    } else {
      $notice_view = null;
    }

    return $notice_view;
  }

  protected function getSideNavFilter() {
    return 'blog';
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();
    $phid = $user->getPHID();

    $blog_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $phid,
      PhabricatorEdgeConfig::TYPE_BLOGGER_HAS_BLOG
    );

    $blogs = id(new PhameBlogQuery())
      ->withPHIDs($blog_phids)
      ->needBloggers(true)
      ->executeWithOffsetPager($this->getPager());

    $this->setBlogs($blogs);

    $this->setPageTitle('My Blogs');

    $this->setShowSideNav(true);

    return $this->buildBlogListPageResponse();
  }

}
