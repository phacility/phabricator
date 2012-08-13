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

  const PAGE_FEED = "feed";
  const PAGE_PROFILE = "profile";

  const FEED_PAGE_SIZE = 20;
  const PROFILE_QUESTION_PAGE_SIZE = 10;
  const PROFILE_ANSWER_PAGE_SIZE = 10;

  static $pages = array(
    self::PAGE_FEED => "Popular Questions",
    self::PAGE_PROFILE => "User Profile"
  );

  public function willProcessRequest(array $data) {
    if (isset($data['page'])) {
      $this->page = $data['page'];
    }

    if (!isset(self::$pages[$this->page])) {
      $this->page = self::PAGE_FEED;
    }

    $this->feedOffset = 0;
    if (isset($data['feedoffset'])) {
      $this->feedOffset = $data['feedoffset'];
    }
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $this->feedOffset = $request->getInt('off');
    $this->questionOffset = $request->getInt('qoff');
    $this->answerOffset = $request->getInt('aoff');

    $side_nav = new AphrontSideNavView();
    foreach (self::$pages as $pagename => $pagetitle) {
      $class = "";
      if ($pagename == $this->page) {
        $class = 'aphront-side-nav-selected';
      }

      $linky = phutil_render_tag(
        'a',
        array(
          'href' => '/ponder/'.$pagename .'/',
          'class' => $class
        ),
        phutil_escape_html($pagetitle)
      );

      $side_nav->addNavItem($linky);
    }

    switch ($this->page) {
      case self::PAGE_FEED:
        $data = PonderQuestionQuery::loadHottest(
          $user,
          $this->feedOffset,
          self::FEED_PAGE_SIZE + 1);

        $phids = array();
        foreach ($data as $question) {
          $phids[] = $question->getAuthorPHID();
        }
        $handles = id(new PhabricatorObjectHandleData($phids))
          ->loadHandles();

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
      case self::PAGE_PROFILE:
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
        $handles = id(new PhabricatorObjectHandleData($phids))
          ->loadHandles();

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
        'title' => self::$pages[$this->page]
      ));
  }

}
