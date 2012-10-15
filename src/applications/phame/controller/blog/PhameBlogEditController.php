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
final class PhameBlogEditController
  extends PhameController {

  private $phid;
  private $isBlogEdit;

  private function setBlogPHID($phid) {
    $this->phid = $phid;
    return $this;
  }
  private function getBlogPHID() {
    return $this->phid;
  }
  private function setIsBlogEdit($is_blog_edit) {
    $this->isBlogEdit = $is_blog_edit;
    return $this;
  }
  private function isBlogEdit() {
    return $this->isBlogEdit;
  }

  protected function getSideNavFilter() {
    if ($this->isBlogEdit()) {
      $filter = 'blog/edit/'.$this->getBlogPHID();
    } else {
      $filter = 'blog/new';
    }
    return $filter;
  }

  protected function getSideNavBlogFilters() {
    $filters = parent::getSideNavBlogFilters();

    if ($this->isBlogEdit()) {
      $filter =
        array('key'  => 'blog/edit/'.$this->getBlogPHID(),
              'name' => 'Edit Blog');
      $filters[] = $filter;
    } else {
      $filter =
        array('key'  => 'blog/new',
              'name' => 'New Blog');
      array_unshift($filters, $filter);
    }

    return $filters;
  }

  public function willProcessRequest(array $data) {
    $phid = idx($data, 'phid');
    $this->setBlogPHID($phid);
    $this->setIsBlogEdit((bool)$phid);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $e_name          = true;
    $e_custom_domain = null;
    $errors          = array();

    if ($this->isBlogEdit()) {
      $blog = id(new PhameBlogQuery())
        ->setViewer($user)
        ->withPHIDs(array($this->getBlogPHID()))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT
          ))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }

      $submit_button  = 'Save Changes';
      $delete_button  = javelin_render_tag(
        'a',
        array(
          'href'  => $blog->getDeleteURI(),
          'class' => 'grey button',
          'sigil' => 'workflow',
        ),
        'Delete Blog');
      $page_title     = 'Edit Blog';
    } else {
      $blog = id(new PhameBlog())
        ->setCreatorPHID($user->getPHID());
      $blogger_tokens = array($user->getPHID() => $user->getFullName());
      $submit_button  = 'Create Blog';
      $delete_button  = null;
      $page_title     = 'Create Blog';
    }

    if ($request->isFormPost()) {
      $name          = $request->getStr('name');
      $description   = $request->getStr('description');
      $custom_domain = $request->getStr('custom_domain');
      $skin          = $request->getStr('skin');

      if (empty($name)) {
        $errors[] = 'You must give the blog a name.';
        $e_name   = 'Required';
      }
      $blog->setName($name);
      $blog->setDescription($description);
      if (!empty($custom_domain)) {
        $error = $blog->validateCustomDomain($custom_domain);
        if ($error) {
          $errors[] = $error;
          $e_custom_domain = 'Invalid';
        }
        $blog->setDomain($custom_domain);
      }
      $blog->setSkin($skin);

      $blog->setViewPolicy($request->getStr('can_view'));
      $blog->setEditPolicy($request->getStr('can_edit'));
      $blog->setJoinPolicy($request->getStr('can_join'));

      // Don't let users remove their ability to edit blogs.
      PhabricatorPolicyFilter::mustRetainCapability(
        $user,
        $blog,
        PhabricatorPolicyCapability::CAN_EDIT);

      if (!$errors) {
        $blog->save();

        $uri = new PhutilURI($blog->getViewURI());
        if ($this->isBlogEdit()) {
          $uri->setQueryParam('edit', true);
        } else {
          $uri->setQueryParam('new', true);
        }
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

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($blog)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Name')
        ->setName('name')
        ->setValue($blog->getName())
        ->setID('blog-name')
        ->setError($e_name)
      )
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setLabel('Description')
        ->setName('description')
        ->setValue($blog->getDescription())
        ->setID('blog-description')
      )
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($blog)
          ->setPolicies($policies)
          ->setName('can_view'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicyObject($blog)
          ->setPolicies($policies)
          ->setName('can_edit'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setCapability(PhabricatorPolicyCapability::CAN_JOIN)
          ->setPolicyObject($blog)
          ->setPolicies($policies)
          ->setName('can_join'))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Custom Domain')
        ->setName('custom_domain')
        ->setValue($blog->getDomain())
        ->setCaption('Must include at least one dot (.), e.g. '.
        'blog.example.com')
        ->setError($e_custom_domain)
      )
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel('Skin')
        ->setName('skin')
        ->setValue($blog->getSkin())
        ->setOptions(PhameBlog::getSkinOptionsForSelect())
      )
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton('/phame/blog/')
        ->setValue($submit_button)
      );

    $panel->appendChild($form);

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    $this->setShowSideNav(true);
    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title'   => $page_title,
      ));
  }
}
