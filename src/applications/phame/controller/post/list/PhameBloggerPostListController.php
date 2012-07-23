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
final class PhameBloggerPostListController
  extends PhamePostListBaseController {

  private $bloggerName;

  private function setBloggerName($blogger_name) {
    $this->bloggerName = $blogger_name;
    return $this;
  }
  private function getBloggerName() {
    return $this->bloggerName;
  }

  public function shouldRequireLogin() {
    // TODO -- get policy logic going
    // return PhabricatorEnv::getEnvConfig('policy.allow-public');
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->setBloggerName(idx($data, 'bloggername'));
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();

    $blogger = id(new PhabricatorUser())->loadOneWhere(
      'username = %s',
      $this->getBloggerName());
    if (!$blogger) {
      return new Aphront404Response();
    }
    $blogger_phid = $blogger->getPHID();
    if ($blogger_phid == $user->getPHID()) {
      $actions    = array('view', 'edit');
    } else {
      $actions    = array('view');
    }
    $this->setActions($actions);

    $query = new PhamePostQuery();
    $query->withBloggerPHID($blogger_phid);
    $query->withVisibility(PhamePost::VISIBILITY_PUBLISHED);
    $this->setPhamePostQuery($query);

    $page_title = 'Posts by '.$this->getBloggerName();
    $this->setPageTitle($page_title);

    $this->setShowSideNav(false);

    return $this->buildPostListPageResponse();
  }
}
