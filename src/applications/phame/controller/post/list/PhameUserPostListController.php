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
final class PhameUserPostListController
  extends PhamePostListBaseController {

  public function shouldRequireLogin() {
    return true;
  }

  protected function getSideNavFilter() {
    return 'post';
  }

  protected function getNoticeView() {
    $user = $this->getRequest()->getUser();

    $new_link = phutil_render_tag(
      'a',
      array(
        'href' => '/phame/post/new/',
        'class' => 'button green',
      ),
      'write another blog post'
    );

    $pretty_uri = PhabricatorEnv::getProductionURI(
      '/phame/posts/'.$user->getUserName().'/');
    $pretty_link = phutil_render_tag(
      'a',
      array(
        'href' => (string) $pretty_uri
      ),
      (string) $pretty_uri
    );

    $notices = array(
      'Seek even more phame and '.$new_link,
      'Published posts also appear at the awesome, world-accessible '.
      'URI: '.$pretty_link
    );

    $notice_view = id(new AphrontErrorView())
      ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
      ->setTitle('Meta thoughts and feelings');
    foreach ($notices as $notice) {
      $notice_view->appendChild('<p>'.$notice.'</p>');
    }

    return $notice_view;
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();
    $phid = $user->getPHID();

    $query = new PhamePostQuery();
    $query->withBloggerPHID($phid);
    $query->withVisibility(PhamePost::VISIBILITY_PUBLISHED);
    $this->setPhamePostQuery($query);

    $actions = array('view', 'edit');
    $this->setActions($actions);

    $this->setPageTitle('My Posts');

    $this->setShowSideNav(true);

    return $this->buildPostListPageResponse();
  }
}
