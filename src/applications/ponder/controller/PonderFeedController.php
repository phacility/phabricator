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
      'feed'    => 'Popular Questions',
      'profile' => 'User Profile',
    );

    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    foreach ($pages as $name => $title) {
      $side_nav->addFilter($name, $title);
    }

    $this->page = $side_nav->selectFilter($this->page, 'feed');

    switch ($this->page) {
      case 'feed':
        $data = PonderQuestionQuery::loadHottest(
          $user,
          $this->feedOffset,
          self::FEED_PAGE_SIZE + 1);

        $phids = array();
        foreach ($data as $question) {
          $phids[] = $question->getAuthorPHID();
        }
        $handles = $this->loadViewerHandles($phids);

        $side_nav->appendChild(
          id(new PonderQuestionFeedView())
          ->setUser($user)
          ->setData($data)
          ->setHandles($handles)
          ->setOffset($this->feedOffset)
          ->setPageSize(self::FEED_PAGE_SIZE)
          ->setURI(new PhutilURI("/ponder/feed/"), "off")
        );
        break;
      case 'profile':
        $questions = PonderQuestionQuery::loadByAuthor(
          $user,
          $user->getPHID(),
          $this->questionOffset,
          self::PROFILE_QUESTION_PAGE_SIZE + 1
        );

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
          ->setQuestions($questions)
          ->setAnswers($answers)
          ->setHandles($handles)
          ->setQuestionOffset($this->questionOffset)
          ->setAnswerOffset($this->answerOffset)
          ->setPageSize(self::PROFILE_QUESTION_PAGE_SIZE)
          ->setURI(new PhutilURI("/ponder/profile/"), "qoff", "aoff")
        );
        break;
    }


    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => $pages[$this->page]
      ));
  }

}
