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

    $table_data = array();
    foreach ($blogs as $blog) {
      $view_link = phutil_render_tag(
        'a',
        array(
          'href' => $blog->getViewURI(),
        ),
        phutil_escape_html($blog->getName()));
      $bloggers = $blog->getBloggers();
      if (isset($bloggers[$user->getPHID()])) {
        $edit = phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => $blog->getEditURI(),
          ),
          'Edit');
      } else {
        $edit = null;
      }
      $view = phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => $blog->getViewURI(),
          ),
          'View');
      $table_data[] =
        array(
          $view_link,
          implode(', ', mpull($blog->getBloggers(), 'renderLink')),
          $view,
          $edit,
        );
    }

    $table = new AphrontTableView($table_data);
    $table->setHeaders(
      array(
        'Name',
        'Bloggers',
        '',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        null,
        'action',
        'action',
      ));

    $panel->setCreateButton('Create a Blog', '/phame/blog/new/');
    $panel->setHeader($this->getHeader());
    $panel->appendChild($table);

    return $panel->render();
  }
}
