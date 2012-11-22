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
 * @group pholio
 */
final class PholioMockViewController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $xactions = id(new PholioTransactionQuery())
      ->withMockIDs(array($mock->getID()))
      ->execute();


    $phids = array();
    $phids[] = $mock->getAuthorPHID();
    foreach ($xactions as $xaction) {
      $phids[] = $xaction->getAuthorPHID();
    }
    $this->loadHandles($phids);


    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    $engine->addObject($mock, PholioMock::MARKUP_FIELD_DESCRIPTION);
    foreach ($xactions as $xaction) {
      $engine->addObject($xaction, PholioTransaction::MARKUP_FIELD_COMMENT);
    }
    $engine->process();

    $title = 'M'.$mock->getID().' '.$mock->getName();

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionView($mock);
    $properties = $this->buildPropertyView($mock, $engine);

    $carousel =
      '<h1 style="margin: 2em; padding: 1em; border: 1px dashed grey;">'.
        'Carousel Goes Here</h1>';
    $comments =
      '<h1 style="margin: 2em; padding: 1em; border: 1px dashed grey;">'.
        'Comments/Transactions Go Here</h1>';


    $xaction_view = $this->buildTransactionView($xactions, $engine);

    $add_comment = $this->buildAddCommentView($mock);

    $content = array(
      $header,
      $actions,
      $properties,
      $carousel,
      $xaction_view,
      $add_comment,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildActionView(PholioMock $mock) {
    $user = $this->getRequest()->getUser();

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($mock);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $mock,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Mock'))
        ->setHref($this->getApplicationURI('/edit/'.$mock->getID()))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

  private function buildPropertyView(
    PholioMock $mock,
    PhabricatorMarkupEngine $engine) {

    $user = $this->getRequest()->getUser();

    $properties = new PhabricatorPropertyListView();

    $properties->addProperty(
      pht('Author'),
      $this->getHandle($mock->getAuthorPHID())->renderLink());

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($mock->getDateCreated(), $user));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $mock);

    $properties->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $properties->addTextContent(
      $engine->getOutput($mock, PholioMock::MARKUP_FIELD_DESCRIPTION));

    return $properties;
  }

  private function buildAddCommentView(PholioMock $mock) {
    $user = $this->getRequest()->getUser();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $title = $is_serious
      ? pht('Add Comment')
      : pht('History Beckons');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $action = $is_serious
      ? pht('Add Comment')
      : pht('Answer The Call');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($this->getApplicationURI('/comment/'.$mock->getID().'/'))
      ->setWorkflow(true)
      ->setFlexible(true)
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('comment')
          ->setLabel(pht('Comment')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($action));

    return array(
      $header,
      $form,
    );
  }

  private function buildTransactionView(
    array $xactions,
    PhabricatorMarkupEngine $engine) {
    assert_instances_of($xactions, 'PholioTransaction');

    $view = new PhabricatorTimelineView();

    foreach ($xactions as $xaction) {
      $author = $this->getHandle($xaction->getAuthorPHID());

      $view->addEvent(
        id(new PhabricatorTimelineEventView())
          ->setUserHandle($author)
          ->setTitle($author->renderLink().' added a comment.')
          ->appendChild(
            $engine->getOutput(
              $xaction,
              PholioTransaction::MARKUP_FIELD_COMMENT)));
    }

    return $view;
  }

}
