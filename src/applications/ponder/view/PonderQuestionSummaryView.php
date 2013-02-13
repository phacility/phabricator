<?php

final class PonderQuestionSummaryView extends AphrontView {
  private $question;
  private $handles;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-feed-view-css');

    $user = $this->user;
    $question = $this->question;
    $author_phid = $question->getAuthorPHID();
    $handles = $this->handles;

    $authorlink = $handles[$author_phid]
      ->renderLink();

    $votecount = hsprintf(
      '<div class="ponder-summary-votes">'.
        '%s'.
        '<div class="ponder-question-label">votes</div>'.
      '</div>',
      $question->getVoteCount());

    $answerclass = "ponder-summary-answers";
    if ($question->getAnswercount() == 0) {
      $answerclass .= " ponder-not-answered";
    }
    $answercount = hsprintf(
      '<div class="ponder-summary-answers">'.
        '%s'.
        '<div class="ponder-question-label">answers</div>'.
      '</div>',
      $question->getAnswerCount());

    $title =
      '<h2 class="ponder-question-title">'.
        phutil_tag(
          'a',
          array(
            "href" => '/Q' . $question->getID(),
          ),
            'Q' . $question->getID() .
            ' ' . $question->getTitle()
        ) .
      '</h2>';

    $rhs =
      '<div class="ponder-metadata">'.
        $title.
       '<span class="ponder-small-metadata">'.
        'asked on '.
        phabricator_datetime($question->getDateCreated(), $user).
        ' by ' . $authorlink.
       '</span>'.
      '</div>';

    $summary =
      '<div class="ponder-question-summary">'.
        $votecount.
        $answercount.
        $rhs.
      '</div>';


    return $summary;
  }
}
