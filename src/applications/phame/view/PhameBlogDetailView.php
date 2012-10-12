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
  private $blog;
  private $bloggers;

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

    $user         = $this->getUser();
    $blog         = $this->getBlog();
    $bloggers     = $this->getBloggers();
    $name         = phutil_escape_html($blog->getName());
    $description  = phutil_escape_html($blog->getDescription());

    $detail = phutil_render_tag(
      'div',
      array(
        'class' => 'blog-detail'
      ),
      phutil_render_tag(
        'div',
        array(
          'class' => 'header',
        ),
        phutil_render_tag(
          'h1',
          array(),
          $name
        )
      ).
      phutil_render_tag(
        'div',
        array(
          'class' => 'description'
        ),
        $description
      ).
      phutil_render_tag(
        'div',
        array(
          'class' => 'bloggers'
        ),
        'Current bloggers: '.$this->getBloggersHTML($bloggers)
      )
    );

    return $detail;
  }

  private function getBloggersHTML(array $bloggers) {
    assert_instances_of($bloggers, 'PhabricatorObjectHandle');

    $arr = array();
    foreach ($bloggers as $blogger) {
      $arr[] = '<strong>'.phutil_escape_html($blogger->getName()).'</strong>';
    }

    return implode(' &middot; ', $arr);
  }
}
