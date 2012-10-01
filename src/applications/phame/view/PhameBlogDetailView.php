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
final class PhameBlogDetailView extends AphrontView {

  private $user;

  private $isAdmin;
  private $blog;
  private $bloggers;

  public function setIsAdmin($is_admin) {
    $this->isAdmin = $is_admin;
    return $this;
  }
  private function getIsAdmin() {
    return $this->isAdmin;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  private function getUser() {
    return $this->user;
  }

  public function setBloggers(array $bloggers) {
    assert_instances_of($bloggers, 'PhabricatorObjectHandle');
    $this->bloggers = $bloggers;
    return $this;
  }
  private function getBloggers() {
    return $this->bloggers;
  }

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }
  private function getBlog() {
    return $this->blog;
  }


  public function render() {
    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('phame-blog-detail-css');

    $user         = $this->getUser();
    $blog         = $this->getBlog();
    $bloggers     = $this->getBloggers();
    $name         = phutil_escape_html($blog->getName());
    $description  = phutil_escape_html($blog->getDescription());
    $bloggers_txt = implode(' &middot; ', mpull($bloggers, 'renderLink'));
    $panel = id(new AphrontPanelView())
      ->addClass('blog-detail')
      ->setHeader($name)
      ->setCaption($description)
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->appendChild('Current bloggers: '.$bloggers_txt);

    if ($this->getIsAdmin()) {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href'  => $blog->getEditURI(),
            'class' => 'button grey',
          ),
          'Edit Blog')
      );

    }

    return $panel->render();
  }

}
