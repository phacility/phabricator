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

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

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
      id(new PhabricatorHeaderView())->setHeader(
        pht('Create Post')));

    if (!$blogs) {
      $notification = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->appendChild(
          pht('You do not have permission to join any blogs. Create a blog '.
              'first, then you can post to it.'));

      $nav->appendChild($notification);
    } else {
      $options = mpull($blogs, 'getName', 'getID');

      $form = id(new AphrontFormView())
        ->setUser($user)
        ->setMethod('GET')
        ->setFlexible(true)
        ->setAction($this->getApplicationURI('post/edit/'))
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel('Blog')
            ->setName('blog')
            ->setOptions($options))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Continue'));

      $nav->appendChild($form);
    }

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => 'Create Post',
        'device'  => true,
      ));
  }
}
