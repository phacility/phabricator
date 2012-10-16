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

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $blog = id(new PhameBlogQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT
          ))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }

      $submit_button = pht('Save Changes');
      $page_title = pht('Edit Blog');
      $cancel_uri = $this->getApplicationURI('blog/view/'.$blog->getID().'/');
    } else {
      $blog = id(new PhameBlog())
        ->setCreatorPHID($user->getPHID());

      $blog->setViewPolicy(PhabricatorPolicies::POLICY_USER);
      $blog->setEditPolicy(PhabricatorPolicies::POLICY_USER);
      $blog->setJoinPolicy(PhabricatorPolicies::POLICY_USER);

      $submit_button = pht('Create Blog');
      $page_title = pht('Create Blog');
      $cancel_uri = $this->getApplicationURI();
    }

    $e_name          = true;
    $e_custom_domain = null;
    $errors          = array();

    if ($request->isFormPost()) {
      $name          = $request->getStr('name');
      $description   = $request->getStr('description');
      $custom_domain = $request->getStr('custom_domain');
      $skin          = $request->getStr('skin');

      if (empty($name)) {
        $errors[] = 'You must give the blog a name.';
        $e_name = 'Required';
      } else {
        $e_name = null;
      }

      $blog->setName($name);
      $blog->setDescription($description);
      $blog->setDomain(nonempty($custom_domain, null));
      $blog->setSkin($skin);

      if (!empty($custom_domain)) {
        $error = $blog->validateCustomDomain($custom_domain);
        if ($error) {
          $errors[] = $error;
          $e_custom_domain = 'Invalid';
        }
      }

      $blog->setViewPolicy($request->getStr('can_view'));
      $blog->setEditPolicy($request->getStr('can_edit'));
      $blog->setJoinPolicy($request->getStr('can_join'));

      // Don't let users remove their ability to edit blogs.
      PhabricatorPolicyFilter::mustRetainCapability(
        $user,
        $blog,
        PhabricatorPolicyCapability::CAN_EDIT);

      if (!$errors) {
        try {
          $blog->save();
          return id(new AphrontRedirectResponse())
            ->setURI($this->getApplicationURI('blog/view/'.$blog->getID().'/'));
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $errors[] = 'Domain must be unique.';
          $e_custom_domain = 'Not Unique';
        }
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($blog)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
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
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button)
      );

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($page_title);

    $nav = $this->renderSideNavFilterView();
    $nav->selectFilter($this->id ? null : 'blog/new');
    $nav->appendChild(
      array(
        $header,
        $error_view,
        $form,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $page_title,
      ));
  }
}
