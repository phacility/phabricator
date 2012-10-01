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

final class PonderFeedController extends PonderController {
  private $page;
  private $feedOffset;
  private $questionOffset;
  private $answerOffset;

  const FEED_PAGE_SIZE = 20;
  const PROFILE_QUESTION_PAGE_SIZE = 10;
  const PROFILE_ANSWER_PAGE_SIZE = 10;

  public function willProcessRequest(array $data) {
    $this->page = idx($data, 'page');
    $this->feedOffset = idx($data, 'feedoffset');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $this->feedOffset = $request->getInt('off');
    $this->questionOffset = $request->getInt('qoff');
    $this->answerOffset = $request->getInt('aoff');

    $pages = array(
      'feed'      => 'All Questions',
      'questions' => 'Your Questions',
      'answers'   => 'Your Answers',
    );

    $side_nav = $this->buildSideNavView();

    $this->page = $side_nav->selectFilter($this->page, 'feed');

    $title = $pages[$this->page];

    switch ($this->page) {
      case 'feed':
      case 'questions':

        if ($this->page == 'feed') {
          $questions = PonderQuestionQuery::loadHottest(
            $user,
            $this->feedOffset,
            self::FEED_PAGE_SIZE + 1);
        } else {
          $questions = PonderQuestionQuery::loadByAuthor(
            $user,
            $user->getPHID(),
            $this->questionOffset,
            self::PROFILE_QUESTION_PAGE_SIZE + 1
          );
        }

        $this->loadHandles(mpull($questions, 'getAuthorPHID'));

        $view = $this->buildQuestionListView($questions);
        $side_nav->appendChild(
          id(new PhabricatorHeaderView())->setHeader($title));
        $side_nav->appendChild($view);
        break;
      case 'answers':
        $answers = PonderAnswerQuery::loadByAuthorWithQuestions(
          $user,
          $user->getPHID(),
          $this->answerOffset,
          self::PROFILE_ANSWER_PAGE_SIZE + 1
        );

        $phids = array($user->getPHID());
        $handles = $this->loadViewerHandles($phids);

        $side_nav->appendChild(
          id(new PonderUserProfileView())
          ->setUser($user)
          ->setAnswers($answers)
          ->setHandles($handles)
          ->setAnswerOffset($this->answerOffset)
          ->setPageSize(self::PROFILE_QUESTION_PAGE_SIZE)
          ->setURI(new PhutilURI("/ponder/profile/"), "aoff")
        );
        break;
    }


    return $this->buildApplicationPage(
      $side_nav,
      array(
        'device'  => true,
        'title'   => $title,
      ));
  }

  private function buildQuestionListView(array $questions) {
    assert_instances_of($questions, 'PonderQuestion');
    $user = $this->getRequest()->getUser();

    $view = new PhabricatorObjectItemListView();
    $view->setNoDataString(pht('No matching questions.'));
    foreach ($questions as $question) {
      $item = new PhabricatorObjectItemView();
      $item->setHeader('Q'.$question->getID().' '.$question->getTitle());
      $item->setHref('/Q'.$question->getID());

      $desc = $question->getContent();
      if ($desc) {
        $item->addDetail(
          pht('Description'),
          phutil_escape_html(phutil_utf8_shorten($desc, 128)));
      }

      $item->addDetail(
        pht('Author'),
        $this->getHandle($question->getAuthorPHID())->renderLink());

      $item->addDetail(
        pht('Votes'),
        $question->getVoteCount());

      $item->addDetail(
        pht('Answers'),
        $question->getAnswerCount());

      $created = pht(
        'Created %s',
        phabricator_date($question->getDateCreated(), $user));
      $item->addAttribute($created);

      $view->addItem($item);
    }

    return $view;
  }

}
