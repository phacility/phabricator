<?php

final class PonderUserProfileView extends AphrontView {
  private $questionoffset;
  private $answeroffset;
  private $answers;
  private $pagesize;
  private $uri;
  private $aparam;

  public function setQuestionOffset($offset) {
    $this->questionoffset = $offset;
    return $this;
  }

  public function setAnswerOffset($offset) {
    $this->answeroffset = $offset;
    return $this;
  }

  public function setAnswers($data) {
    $this->answers = $data;
    return $this;
  }

  public function setPageSize($pagesize) {
    $this->pagesize = $pagesize;
    return $this;
  }

  public function setURI($uri, $aparam) {
    $this->uri = $uri;
    $this->aparam = $aparam;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-core-view-css');
    require_celerity_resource('ponder-feed-view-css');

    $user     = $this->user;
    $aoffset  = $this->answeroffset;
    $answers  = $this->answers;
    $uri      = $this->uri;
    $aparam   = $this->aparam;
    $pagesize = $this->pagesize;

    $apagebuttons = id(new AphrontPagerView())
      ->setPageSize($pagesize)
      ->setOffset($aoffset)
      ->setURI(
        $uri
          ->setFragment('answers'),
        $aparam);
    $answers = $apagebuttons->sliceResults($answers);

    $view = new PhabricatorObjectItemListView();
    $view->setUser($user);
    $view->setNoDataString(pht('No matching answers.'));

    foreach ($answers as $answer) {
      $question    = $answer->getQuestion();
      $author_phid = $question->getAuthorPHID();

      $item = new PhabricatorObjectItemView();
      $item->setObject($answer);
      $href = id(new PhutilURI('/Q' . $question->getID()))
        ->setFragment('A' . $answer->getID());
      $item->setHeader(
        'A'.$answer->getID().' '.self::abbreviate($answer->getContent()));
      $item->setHref($href);

      $item->addAttribute(
        pht('Created %s', phabricator_date($answer->getDateCreated(), $user)));

      $item->addAttribute(pht('%d Vote(s)', $answer->getVoteCount()));

      $item->addAttribute(
        pht(
          'Answer to %s',
          phutil_tag(
            'a',
            array(
              'href' => '/Q'.$question->getID(),
            ),
            self::abbreviate($question->getTitle()))));

      $view->addItem($item);
    }

    $view->appendChild($apagebuttons);

    return $view->render();
  }

  private function abbreviate($w) {
    return phutil_utf8_shorten($w, 60);
  }
}
