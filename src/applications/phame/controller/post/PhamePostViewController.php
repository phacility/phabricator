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
final class PhamePostViewController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$post) {
      return new Aphront404Response();
    }

    if ($post->isDraft()) {
      $notice = array(
        'title' => 'You are previewing a draft.',
        'body'  => 'Only you can see this draft until you publish it. '.
                   'If you chose a comment widget it will show up when '.
                   'you publish.'
      );
    } else if ($request->getExists('saved')) {
      $new_link = phutil_render_tag(
        'a',
        array(
          'href' => '/phame/post/new/',
          'class' => 'button green',
        ),
        'write another blog post'
      );
      $notice = array(
        'title' => 'Saved post successfully.',
        'body'  => 'Seek even more phame and '.$new_link.'.'
      );
    } else {
      $notice = array();
    }

    $this->loadHandles(
      array(
        $post->getBlogPHID(),
        $post->getBloggerPHID(),
      ));

    $nav = $this->renderSideNavFilterView(null);

    $header = id(new PhabricatorHeaderView())->setHeader($post->getTitle());

    $actions = $this->renderActions($post, $user);
    $properties = $this->renderProperties($post, $user);

    $nav->appendChild(
      array(
        $header,
        $actions,
        $properties,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $post->getTitle(),
        'device'  => true,
      ));
  }

  private function renderActions(
    PhamePost $post,
    PhabricatorUser $user) {

    $actions = id(new PhabricatorActionListView())
      ->setObject($post)
      ->setUser($user);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $post,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $post->getID();

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setHref($this->getApplicationURI('post/edit/'.$id.'/'))
        ->setName('Edit Post')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $blog = $post->getBlog();
    $can_view_live = $blog && !$post->isDraft();

    if ($can_view_live) {
      $live_uri = 'live/'.$blog->getID().'/post/'.$post->getPhameTitle();
    } else {
      $live_uri = 'post/notlive/'.$post->getID().'/';
    }
    $live_uri = $this->getApplicationURI($live_uri);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('world')
        ->setHref($live_uri)
        ->setName(pht('View Live'))
        ->setDisabled(!$can_view_live)
        ->setWorkflow(!$can_view_live));

    if ($post->isDraft()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('world')
          ->setHref($this->getApplicationURI('post/publish/'.$id.'/'))
          ->setName(pht('Preview / Publish')));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('delete')
          ->setHref($this->getApplicationURI('post/unpublish/'.$id.'/'))
          ->setName(pht('Unpublish'))
          ->setWorkflow(true));
    }

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('delete')
        ->setHref($this->getApplicationURI('post/delete/'.$id.'/'))
        ->setName('Delete Post')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $actions;
  }

  private function renderProperties(
    PhamePost $post,
    PhabricatorUser $user) {

    $properties = new PhabricatorPropertyListView();

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $post);

    $properties->addProperty(
      pht('Blog'),
      $post->getBlogPHID()
        ? $this->getHandle($post->getBlogPHID())->renderLink()
        : null);

    $properties->addProperty(
      pht('Blogger'),
      $this->getHandle($post->getBloggerPHID())->renderLink());

    $properties->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $properties->addProperty(
      pht('Published'),
      $post->isDraft()
        ? pht('Draft')
        : phabricator_datetime($post->getDatePublished(), $user));

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user)
      ->addObject($post, PhamePost::MARKUP_FIELD_BODY)
      ->process();

    $properties->addTextContent(
      '<div class="phabricator-remarkup">'.
        $engine->getOutput($post, PhamePost::MARKUP_FIELD_BODY).
      '</div>');

    return $properties;
  }
}
