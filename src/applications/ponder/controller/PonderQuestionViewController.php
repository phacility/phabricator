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

final class PonderQuestionViewController extends PonderController {

  private $questionID;

  public function willProcessRequest(array $data) {
    $this->questionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $question = PonderQuestionQuery::loadSingle($user, $this->questionID);
    if (!$question) {
      return new Aphront404Response();
    }
    $question->attachRelated();
    $question->attachVotes($user->getPHID());
    $object_phids = array($user->getPHID(), $question->getAuthorPHID());

    $answers = $question->getAnswers();
    $comments = $question->getComments();
    foreach ($comments as $comment) {
      $object_phids[] = $comment->getAuthorPHID();
    }

    foreach ($answers as $answer) {
      $object_phids[] = $answer->getAuthorPHID();

      $comments = $answer->getComments();
      foreach ($comments as $comment) {
        $object_phids[] = $comment->getAuthorPHID();
      }
    }

    $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $question->getPHID());

    $object_phids = array_merge($object_phids, $subscribers);

    $handles = $this->loadViewerHandles($object_phids);
    $this->loadHandles($object_phids);

    $detail_panel = new PonderQuestionDetailView();
    $detail_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setHandles($handles);

    $responses_panel = new PonderAnswerListView();
    $responses_panel
      ->setQuestion($question)
      ->setHandles($handles)
      ->setUser($user)
      ->setAnswers($answers);

    $answer_add_panel = new PonderAddAnswerView();
    $answer_add_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setActionURI("/ponder/answer/add/");

    $header = id(new PhabricatorHeaderView())
      ->setObjectName('Q'.$question->getID())
      ->setHeader($question->getTitle());

    $actions = $this->buildActionListView($question);
    $properties = $this->buildPropertyListView($question, $subscribers);

    $nav = $this->buildSideNavView($question);
    $nav->appendChild(
      array(
        $header,
        $actions,
        $properties,
        $detail_panel,
        $responses_panel,
        $answer_add_panel
      ));
    $nav->selectFilter(null);


    return $this->buildApplicationPage(
      $nav,
      array(
        'device' => true,
        'title' => 'Q'.$question->getID().' '.$question->getTitle()
      ));
  }

  private function buildActionListView(PonderQuestion $question) {
    $viewer = $this->getRequest()->getUser();
    $view = new PhabricatorActionListView();

    $view->setUser($viewer);
    $view->setObject($question);

    return $view;
  }

  private function buildPropertyListView(
    PonderQuestion $question,
    array $subscribers) {

    $viewer = $this->getRequest()->getUser();
    $view = new PhabricatorPropertyListView();

    $view->addProperty(
      pht('Author'),
      $this->getHandle($question->getAuthorPHID())->renderLink());

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($question->getDateCreated(), $viewer));

    if ($subscribers) {
      foreach ($subscribers as $key => $subscriber) {
        $subscribers[$key] = $this->getHandle($subscriber)->renderLink();
      }
      $subscribers = implode(', ', $subscribers);
    }

    $view->addProperty(
      pht('Subscribers'),
      nonempty($subscribers, '<em>'.pht('None').'</em>'));

    return $view;
  }
}
