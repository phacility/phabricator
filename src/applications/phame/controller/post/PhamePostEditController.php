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
final class PhamePostEditController
  extends PhameController {

  private $phid;
  private $isPostEdit;
  private $userBlogs;
  private $postBlogs;
  private $post;

  private function setPost(PhamePost $post) {
    $this->post = $post;
    return $this;
  }
  private function getPost() {
    return $this->post;
  }

  private function setPostPHID($phid) {
    $this->phid = $phid;
    return $this;
  }
  private function getPostPHID() {
    return $this->phid;
  }
  private function setIsPostEdit($is_post_edit) {
    $this->isPostEdit = $is_post_edit;
    return $this;
  }
  private function isPostEdit() {
    return $this->isPostEdit;
  }
  private function setUserBlogs(array $blogs) {
    assert_instances_of($blogs, 'PhameBlog');
    $this->userBlogs = $blogs;
    return $this;
  }
  private function getUserBlogs() {
    return $this->userBlogs;
  }
  private function setPostBlogs(array $blogs) {
    assert_instances_of($blogs, 'PhameBlog');
    $this->postBlogs = $blogs;
    return $this;
  }
  private function getPostBlogs() {
    return $this->postBlogs;
  }

  protected function getSideNavFilter() {
    if ($this->isPostEdit()) {
      $post_noun = $this->getPost()->getHumanName();
      $filter    = $post_noun.'/edit/'.$this->getPostPHID();
    } else {
      $filter = 'post/new';
    }
    return $filter;
  }
  protected function getSideNavExtraPostFilters() {
    if ($this->isPostEdit() && !$this->getPost()->isDraft()) {
      $filters = array(
        array('key'  => 'post/edit/'.$this->getPostPHID(),
              'name' => 'Edit Post')
      );
    } else {
      $filters = array();
    }

    return $filters;
  }
  protected function getSideNavExtraDraftFilters() {
    if ($this->isPostEdit() && $this->getPost()->isDraft()) {
      $filters = array(
        array('key'  => 'draft/edit/'.$this->getPostPHID(),
              'name' => 'Edit Draft')
      );
    } else {
      $filters = array();
    }

    return $filters;
  }

  public function willProcessRequest(array $data) {
    $phid = idx($data, 'phid');
    $this->setPostPHID($phid);
    $this->setIsPostEdit((bool) $phid);
  }

  public function processRequest() {
    $request       = $this->getRequest();
    $user          = $request->getUser();
    $e_phame_title = null;
    $e_title       = null;
    $errors        = array();

    if ($this->isPostEdit()) {
      $posts = id(new PhamePostQuery())
        ->withPHIDs(array($this->getPostPHID()))
        ->execute();
      $post  = reset($posts);
      if (empty($post)) {
        return new Aphront404Response();
      }
      if ($post->getBloggerPHID() != $user->getPHID()) {
        return new Aphront403Response();
      }
      $post_noun     = ucfirst($post->getHumanName());
      $cancel_uri    = $post->getViewURI($user->getUsername());
      $submit_button = 'Save Changes';
      $delete_button = javelin_render_tag(
        'a',
        array(
          'href'  => $post->getDeleteURI(),
          'class' => 'grey button',
          'sigil' => 'workflow',
        ),
        'Delete '.$post_noun);
      $page_title    = 'Edit '.$post_noun;
    } else {
      $post = id(new PhamePost())
        ->setBloggerPHID($user->getPHID())
        ->setVisibility(PhamePost::VISIBILITY_DRAFT);
      $cancel_uri    = '/phame/draft/';
      $submit_button = 'Create Draft';
      $delete_button = null;
      $page_title    = 'Create Draft';
    }
    $this->setPost($post);
    $this->loadEdgesAndBlogs();

    if ($request->isFormPost()) {
      $saved       = true;
      $visibility  = $request->getInt('visibility');
      $comments    = $request->getStr('comments_widget');
      $data        = array('comments_widget' => $comments);
      $phame_title = $request->getStr('phame_title');
      $phame_title = PhabricatorSlug::normalize($phame_title);
      $title       = $request->getStr('title');
      $post->setTitle($title);
      $post->setPhameTitle($phame_title);
      $post->setBody($request->getStr('body'));
      $post->setVisibility($visibility);
      $post->setConfigData($data);
      // only publish once...!
      if ($visibility == PhamePost::VISIBILITY_PUBLISHED) {
        if (!$post->getDatePublished()) {
          $post->setDatePublished(time());
        }
      // this is basically a cast of null to 0 if its a new post
      } else if (!$post->getDatePublished()) {
        $post->setDatePublished(0);
      }
      if ($phame_title == '/') {
        $errors[]      = 'Phame title must be nonempty.';
        $e_phame_title = 'Required';
      }
      if (empty($title)) {
        $errors[] = 'Title must be nonempty.';
        $e_title  = 'Required';
      }

      $blogs_published    = array_keys($this->getPostBlogs());
      $blogs_to_publish   = array();
      $blogs_to_depublish = array();
      if ($visibility == PhamePost::VISIBILITY_PUBLISHED) {
        $blogs_arr          = $request->getArr('blogs');
        $blogs_to_publish   = array_values($blogs_arr);
        $blogs_to_depublish = array_diff($blogs_published,
                                         $blogs_to_publish);
      } else {
        $blogs_to_depublish   = $blogs_published;
      }

      if (empty($errors)) {
        try {
          $post->save();

          $editor    = new PhabricatorEdgeEditor();
          $edge_type = PhabricatorEdgeConfig::TYPE_POST_HAS_BLOG;
          $editor->setActor($user);
          foreach ($blogs_to_publish as $phid) {
            $editor->addEdge($post->getPHID(), $edge_type, $phid);
          }
          foreach ($blogs_to_depublish as $phid) {
            $editor->removeEdge($post->getPHID(), $edge_type, $phid);
          }
          $editor->save();

        } catch (AphrontQueryDuplicateKeyException $e) {
          $saved         = false;
          $e_phame_title = 'Not Unique';
          $errors[]      = 'Another post already uses this slug. '.
                           'Each post must have a unique slug.';
        }
      } else {
        $saved = false;
      }

      if ($saved) {
        $uri = new PhutilURI($post->getViewURI($user->getUsername()));
        $uri->setQueryParam('saved', true);
        return id(new AphrontRedirectResponse())
          ->setURI($uri);
      }
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($page_title);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    if ($delete_button) {
      $panel->addButton($delete_button);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Title')
        ->setName('title')
        ->setValue($post->getTitle())
        ->setID('post-title')
        ->setError($e_title)
      )
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Phame Title')
        ->setName('phame_title')
        ->setValue(rtrim($post->getPhameTitle(), '/'))
        ->setID('post-phame-title')
        ->setCaption('Up to 64 alphanumeric characters '.
                     'with underscores for spaces. '.
                     'Formatting is enforced.')
        ->setError($e_phame_title)
      )
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setLabel('Body')
        ->setName('body')
        ->setValue($post->getBody())
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setID('post-body')
      )
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel('Visibility')
        ->setName('visibility')
        ->setValue($post->getVisibility())
        ->setOptions(PhamePost::getVisibilityOptionsForSelect())
        ->setID('post-visibility')
      )
      ->appendChild(
        $this->getBlogCheckboxControl($post)
      )
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel('Comments Widget')
        ->setName('comments_widget')
        ->setvalue($post->getCommentsWidget())
        ->setOptions($post->getCommentsWidgetOptionsForSelect())
      )
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button)
      );

    $panel->appendChild($form);

    $preview_panel =
      '<div class="aphront-panel-preview ">
         <div class="phame-post-preview-header">
           Post Preview
         </div>
         <div id="post-preview">
           <div class="aphront-panel-preview-loading-text">
             Loading preview...
           </div>
         </div>
       </div>';

    Javelin::initBehavior(
      'phame-post-preview',
      array(
        'preview'     => 'post-preview',
        'body'        => 'post-body',
        'title'       => 'post-title',
        'phame_title' => 'post-phame-title',
        'uri'         => '/phame/post/preview/',
      ));

    $visibility_data = array(
      'select_id'   => 'post-visibility',
      'current'     => $post->getVisibility(),
      'published'   => PhamePost::VISIBILITY_PUBLISHED,
      'draft'       => PhamePost::VISIBILITY_DRAFT,
      'change_uri'  => $post->getChangeVisibilityURI(),
    );

    $blogs_data = array(
      'checkbox_id'    => 'post-blogs',
      'have_published' => (bool) count($this->getPostBlogs())
    );

    Javelin::initBehavior(
      'phame-post-blogs',
      array(
        'blogs'               => $blogs_data,
        'visibility'          => $visibility_data,
      ));

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Errors saving post.')
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    $this->setShowSideNav(true);
    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
        $preview_panel,
      ),
      array(
        'title'   => $page_title,
      ));
  }

  private function getBlogCheckboxControl(PhamePost $post) {
    if ($post->getVisibility() == PhamePost::VISIBILITY_PUBLISHED) {
      $control_style = null;
    } else {
      $control_style = 'display: none';
    }

    $control = id(new AphrontFormCheckboxControl())
      ->setLabel('Blogs')
      ->setControlID('post-blogs')
      ->setControlStyle($control_style);

    $post_blogs = $this->getPostBlogs();
    $user_blogs = $this->getUserBlogs();
    $all_blogs  = $post_blogs + $user_blogs;
    $all_blogs  = msort($all_blogs, 'getName');
    foreach ($all_blogs as $phid => $blog) {
      $control->addCheckbox(
        'blogs[]',
        $blog->getPHID(),
        $blog->getName(),
        isset($post_blogs[$phid])
      );
    }

    return $control;
  }

  private function loadEdgesAndBlogs() {
    $edge_types   = array(PhabricatorEdgeConfig::TYPE_BLOGGER_HAS_BLOG);
    $blogger_phid = $this->getRequest()->getUser()->getPHID();
    $phids        = array($blogger_phid);
    if ($this->isPostEdit()) {
      $edge_types[] = PhabricatorEdgeConfig::TYPE_POST_HAS_BLOG;
      $phids[]      = $this->getPostPHID();
    }

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($phids)
      ->withEdgeTypes($edge_types)
      ->execute();

    $all_blogs_assoc = array();
    foreach ($phids as $phid) {
      foreach ($edge_types as $type) {
        $all_blogs_assoc += $edges[$phid][$type];
      }
    }

    $blogs = id(new PhameBlogQuery())
      ->withPHIDs(array_keys($all_blogs_assoc))
      ->execute();
    $blogs = mpull($blogs, null, 'getPHID');

    $user_blogs = array_intersect_key(
      $blogs,
      $edges[$blogger_phid][PhabricatorEdgeConfig::TYPE_BLOGGER_HAS_BLOG]
    );

    if ($this->isPostEdit()) {
      $post_blogs = array_intersect_key(
        $blogs,
        $edges[$this->getPostPHID()][PhabricatorEdgeConfig::TYPE_POST_HAS_BLOG]
      );
    } else {
      $post_blogs = array();
    }

    $this->setUserBlogs($user_blogs);
    $this->setPostBlogs($post_blogs);
  }
}
