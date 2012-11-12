<?php

final class PonderFeedController extends PonderController {
  private $page;
  private $answerOffset;

  const PROFILE_ANSWER_PAGE_SIZE = 10;

  public function willProcessRequest(array $data) {
    $this->page = idx($data, 'page');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
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
        $pager = new AphrontPagerView();
        $pager->setOffset($request->getStr('offset'));
        $pager->setURI($request->getRequestURI(), 'offset');

        $query = new PonderQuestionQuery();

        if ($this->page == 'feed') {
          $query
            ->setOrder(PonderQuestionQuery::ORDER_HOTTEST);
        } else {
          $query
            ->setOrder(PonderQuestionQuery::ORDER_CREATED)
            ->withAuthorPHIDs(array($user->getPHID()));
        }

        $questions = $query->executeWithOffsetPager($pager);

        $this->loadHandles(mpull($questions, 'getAuthorPHID'));

        $view = $this->buildQuestionListView($questions);
        $view->setPager($pager);

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

        $side_nav->appendChild(
          id(new PonderUserProfileView())
          ->setUser($user)
          ->setAnswers($answers)
          ->setAnswerOffset($this->answerOffset)
          ->setPageSize(self::PROFILE_ANSWER_PAGE_SIZE)
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
