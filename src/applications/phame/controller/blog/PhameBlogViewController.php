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
final class PhameBlogViewController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->executeWithCursorPager($pager);

    $nav = $this->renderSideNavFilterView(null);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($blog->getName());

    $handle_phids = array_merge(
      mpull($posts, 'getBloggerPHID'),
      mpull($posts, 'getBlogPHID'));
    $this->loadHandles($handle_phids);

    $actions = $this->renderActions($blog, $user);
    $properties = $this->renderProperties($blog, $user);
    $post_list = $this->renderPostList(
      $posts,
      $user,
      pht('This blog has no visible posts.'));

    $nav->appendChild(
      array(
        $header,
        $actions,
        $properties,
        $post_list,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'device'  => true,
        'title'   => $blog->getName(),
      ));
  }

  private function renderProperties(PhameBlog $blog, PhabricatorUser $user) {
    $properties = new PhabricatorPropertyListView();

    $properties->addProperty(
      pht('Skin'),
      phutil_escape_html($blog->getSkin()));

    $properties->addProperty(
      pht('Domain'),
      phutil_escape_html($blog->getDomain()));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $blog);

    $properties->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $properties->addProperty(
      pht('Joinable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_JOIN]);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user)
      ->addObject($blog, PhameBlog::MARKUP_FIELD_DESCRIPTION)
      ->process();

    $properties->addTextContent(
      '<div class="phabricator-remarkup">'.
        $engine->getOutput($blog, PhameBlog::MARKUP_FIELD_DESCRIPTION).
      '</div>');

    return $properties;
  }

  private function renderActions(PhameBlog $blog, PhabricatorUser $user) {

    $actions = id(new PhabricatorActionListView())
      ->setObject($blog)
      ->setUser($user);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $blog,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_join = PhabricatorPolicyFilter::hasCapability(
      $user,
      $blog,
      PhabricatorPolicyCapability::CAN_JOIN);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('new')
        ->setHref($this->getApplicationURI('post/new/?blog='.$blog->getID()))
        ->setName(pht('Write Post'))
        ->setDisabled(!$can_join)
        ->setWorkflow(!$can_join));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('world')
        ->setHref($this->getApplicationURI('live/'.$blog->getID().'/'))
        ->setName(pht('View Live')));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setHref($this->getApplicationURI('blog/edit/'.$blog->getID().'/'))
        ->setName('Edit Blog')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('delete')
        ->setHref($this->getApplicationURI('blog/delete/'.$blog->getID().'/'))
        ->setName('Delete Blog')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $actions;
  }

}
