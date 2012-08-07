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
final class PhameAllPostListController
  extends PhamePostListBaseController {

  public function shouldRequireLogin() {
    return true;
  }

  protected function getSideNavFilter() {
    return 'post/all';
  }

  protected function getNoticeView() {
    $user = $this->getRequest()->getUser();

    $new_link = phutil_render_tag(
      'a',
      array(
        'href' => '/phame/post/new/',
        'class' => 'button green',
      ),
      'write a blog post'
    );

    $remarkup_link = phutil_render_tag(
      'a',
      array(
        'href' =>
          PhabricatorEnv::getDoclink('article/Remarkup_Reference.html'),
      ),
      'remarkup'
    );

    $guide_link = phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink('article/Phame_User_Guide.html'),
      ),
      'Phame user guide'
    );

    $notices = array(
      'Seek phame and '.$new_link,
      'Use '.$remarkup_link.' for maximal elegance, grace, and style. ',
      'If you need more help try the '.$guide_link.'.',
    );

    $notice_view = $this->buildNoticeView();
    foreach ($notices as $notice) {
      $notice_view->appendChild('<p>'.$notice.'</p>');
    }

    return $notice_view;
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();

    $query = new PhamePostQuery();
    $query->withVisibility(PhamePost::VISIBILITY_PUBLISHED);
    $this->setPhamePostQuery($query);

    $this->setActions(array('view'));

    $page_title = 'All Posts';
    $this->setPageTitle($page_title);

    $this->setShowSideNav(true);

    return $this->buildPostListPageResponse();
  }

}
