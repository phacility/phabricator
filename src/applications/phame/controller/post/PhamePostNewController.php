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
final class PhamePostNewController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $post = null;
    $view_uri = null;
    if ($this->id) {
      $post = id(new PhamePostQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }

      $view_uri = '/post/view/'.$post->getID().'/';
      $view_uri = $this->getApplicationURI($view_uri);

      if ($request->isFormPost()) {
        $blog = id(new PhameBlogQuery())
          ->setViewer($user)
          ->withIDs(array($request->getInt('blog')))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_JOIN,
            ))
          ->executeOne();

        if ($blog) {
          $post->setBlogPHID($blog->getPHID());
          $post->save();

          return id(new AphrontRedirectResponse())->setURI($view_uri);
        }
      }

      $title = pht('Move Post');
    } else {
      $title = pht('Create Post');
    }

    $blogs = id(new PhameBlogQuery())
      ->setViewer($user)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_JOIN,
        ))
      ->execute();

    $nav = $this->renderSideNavFilterView();
    $nav->selectFilter('post/new');
    $nav->appendChild(
      id(new PhabricatorHeaderView())->setHeader($title));

    if (!$blogs) {
      $notification = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->appendChild(
          pht('You do not have permission to join any blogs. Create a blog '.
              'first, then you can post to it.'));

      $nav->appendChild($notification);
    } else {
      $options = mpull($blogs, 'getName', 'getID');
      asort($options);

      $selected_value = null;
      if ($post && $post->getBlog()) {
        $selected_value = $post->getBlog()->getID();
      }

      $form = id(new AphrontFormView())
        ->setUser($user)
        ->setFlexible(true)
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel('Blog')
            ->setName('blog')
            ->setOptions($options)
            ->setValue($selected_value));

      if ($post) {
        $form
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Move Post'))
              ->addCancelButton($view_uri));
      } else {
        $form
          ->setAction($this->getApplicationURI('post/edit/'))
          ->setMethod('GET')
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Continue')));
      }

      $nav->appendChild($form);
    }

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $title,
        'device'  => true,
      ));
  }
}
