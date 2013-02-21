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

    $title = hsprintf('<h2 class="ponder-question-title">%s</h2>',
      phutil_tag(
        'a',
        array(
          "href" => '/Q' . $question->getID(),
        ),
          'Q' . $question->getID() .
          ' ' . $question->getTitle()));

    $rhs = hsprintf(
      '<div class="ponder-metadata">'.
        '%s <span class="ponder-small-metadata">asked on %s by %s</span>'.
      '</div>',
      $title,
      phabricator_datetime($question->getDateCreated(), $user),
      $authorlink);

    $summary = hsprintf(
      '<div class="ponder-question-summary">%s%s%s</div>',
      $votecount,
      $answercount,
      $rhs);


    return $summary;
  }
}
