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
final class PhamePostListView extends AphrontView {

  private $user;
  private $posts;
  private $bloggers;
  private $actions;
  private $draftList;
  private $blogStyle;

  public function setDraftList($draft_list) {
    $this->draftList = $draft_list;
    return $this;
  }
  public function isDraftList() {
    return (bool) $this->draftList;
  }
  private function getPostNoun() {
    if ($this->isDraftList()) {
      $noun = 'Draft';
    } else {
      $noun = 'Post';
    }
    return $noun;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  private function getUser() {
    return $this->user;
  }
  public function setPosts(array $posts) {
    assert_instances_of($posts, 'PhamePost');
    $this->posts = $posts;
    return $this;
  }
  private function getPosts() {
    return $this->posts;
  }
  public function setBloggers(array $bloggers) {
    assert_instances_of($bloggers, 'PhabricatorObjectHandle');
    $this->bloggers = $bloggers;
    return $this;
  }
  private function getBloggers() {
    return $this->bloggers;
  }
  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }
  private function getActions() {
    if ($this->actions) {
      return $this->actions;
    }
    return array();
  }

  public function setBlogStyle($style) {
    $this->blogStyle = $style;
    return $this;
  }
  private function getBlogStyle() {
    return $this->blogStyle;
  }

  public function render() {
    $user       = $this->getUser();
    $posts      = $this->getPosts();
    $bloggers   = $this->getBloggers();
    $noun       = $this->getPostNoun();
    // TODO -- change this from a boolean to a string
    // this string will represent a more specific "style" below
    $blog_style = $this->getBlogStyle();

    if (empty($posts)) {
      $panel = id(new AphrontPanelView())
        ->setHeader(sprintf('No %ss... Yet!', $noun))
        ->setCaption('Will you answer the call to phame?')
        ->setCreateButton(sprintf('New %s', $noun),
                          sprintf('/phame/%s/new', strtolower($noun)));
      return $panel->render();
    }
    require_celerity_resource('phabricator-remarkup-css');
    if ($blog_style) {
      require_celerity_resource('phame-blog-post-list-css');
    }

    $engine  = PhabricatorMarkupEngine::newPhameMarkupEngine();
    $html    = array();
    $actions = $this->getActions();
    foreach ($posts as $post) {
      $blogger_phid = $post->getBloggerPHID();
      $blogger      = $bloggers[$blogger_phid];
      $blogger_link = $blogger->renderLink();
      $updated      = phabricator_datetime($post->getDateModified(),
                                           $user);
      $body         = $engine->markupText($post->getBody());
      $panel        = id(new AphrontPanelView())
        ->setHeader(phutil_escape_html($post->getTitle()))
        ->setCaption('Last updated '.$updated.' by '.$blogger_link.'.')
        ->appendChild('<div class="phabricator-remarkup">'.$body.'</div>');
      if ($blog_style) {
        $panel->addClass('blog-post-list');
      }
      foreach ($actions as $action) {
        switch ($action) {
          case 'view':
            $uri   = $post->getViewURI($blogger->getName());
            $label = 'View '.$noun;
            break;
          case 'edit':
            $uri   = $post->getEditURI();
            $label = 'Edit '.$noun;
            break;
          default:
            break;
        }
        $button = phutil_render_tag(
          'a',
          array(
            'href'  => $uri,
            'class' => 'grey button',
          ),
          $label);
        $panel->addButton($button);
      }

      $html[] = $panel->render();
    }

    return implode('', $html);
  }
}
