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
final class PhameBlogListView extends AphrontView {

  private $user;
  private $blogs;
  private $header;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }
  private function getHeader() {
    return $this->header;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  private function getUser() {
    return $this->user;
  }

  public function setBlogs(array $blogs) {
    assert_instances_of($blogs, 'PhameBlog');
    $this->blogs = $blogs;
    return $this;
  }
  private function getBlogs() {
    return $this->blogs;
  }

  public function render() {
    $user  = $this->getUser();
    $blogs = $this->getBlogs();
    $panel = new AphrontPanelView();

    if (empty($blogs)) {
      $panel = id(new AphrontPanelView())
        ->setHeader('No blogs... Yet!')
        ->setCaption('Will you answer the call to phame?')
        ->setCreateButton('New Blog',
                          '/phame/blog/new');
      return $panel->render();
    }

    $view = new PhabricatorObjectItemListView();
    foreach ($blogs as $blog) {
      $bloggers = $blog->getBloggers();

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($blog->getName())
        ->setHref($blog->getViewURI())
        ->addDetail(
          'Bloggers',
          implode(', ', mpull($bloggers, 'renderLink')))
        ->addDetail(
          'Custom Domain',
          $blog->getDomain());

      if (isset($bloggers[$user->getPHID()])) {
        $item->addAttribute(
          phutil_render_tag(
            'a',
            array(
              'class' => 'button small grey',
              'href'  => $blog->getEditURI(),
            ),
            'Edit'));
      }

      $view->addItem($item);
    }

    $panel->setCreateButton('Create a Blog', '/phame/blog/new/');
    $panel->setHeader($this->getHeader());
    $panel->appendChild($view);

    return $panel->render();
  }
}
