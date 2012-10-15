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
      $post = id(new PhamePostQuery())
        ->setViewer($user)
        ->withPHIDs(array($this->getPostPHID()))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
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
      $blog = id(new PhameBlogQuery())
        ->setViewer($user)
        ->withIDs(array($request->getInt('blog')))
        ->executeOne();

      if (!$blog) {
        return new Aphront404Response();
      }

      $post = id(new PhamePost())
        ->setBloggerPHID($user->getPHID())
        ->setBlog($blog)
        ->setBlogPHID($blog->getPHID())
        ->setVisibility(PhamePost::VISIBILITY_DRAFT);
      $cancel_uri = $this->getApplicationURI('/blog/view/'.$blog->getID().'/');

      $submit_button = pht('Create Draft');
      $delete_button = null;
      $page_title    = pht('Create Draft');
    }

    $this->setPost($post);

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

      if (!$errors) {
        try {
          $post->save();

          $uri = new PhutilURI($post->getViewURI($user->getUsername()));
          $uri->setQueryParam('saved', true);
          return id(new AphrontRedirectResponse())
            ->setURI($uri);
        } catch (AphrontQueryDuplicateKeyException $e) {
          $e_phame_title = 'Not Unique';
          $errors[]      = 'Another post already uses this slug. '.
                           'Each post must have a unique slug.';
        }
      }
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($page_title);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    if ($delete_button) {
      $panel->addButton($delete_button);
    }

    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $post->getBlogPHID(),
      $user);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Blog')
          ->setValue($handle->renderLink()))
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

    require_celerity_resource('phame-css');
    Javelin::initBehavior(
      'phame-post-preview',
      array(
        'preview'     => 'post-preview',
        'body'        => 'post-body',
        'title'       => 'post-title',
        'phame_title' => 'post-phame-title',
        'uri'         => '/phame/post/preview/',
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

}
