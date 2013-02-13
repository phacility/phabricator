<?php

final class PonderAnswerListView extends AphrontView {

  private $question;
  private $handles;
  private $answers;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setAnswers(array $answers) {
    assert_instances_of($answers, 'PonderAnswer');

    $this->answers = array();

    // group by descreasing score, randomizing
    // order within groups
    $by_score = mgroup($answers, 'getVoteCount');
    $scores = array_keys($by_score);
    rsort($scores);

    foreach ($scores as $score) {
      $group = $by_score[$score];
      shuffle($group);
      foreach ($group as $cur_answer) {
        $this->answers[] = $cur_answer;
      }
    }

    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-post-css');

    $question = $this->question;
    $user = $this->user;
    $handles = $this->handles;

    $panel = id(new AphrontPanelView())
      ->addClass("ponder-panel")
      ->setHeader("Responses:");

    foreach ($this->answers as $cur_answer) {
      $view = new PonderPostBodyView();
      $view
        ->setQuestion($question)
        ->setTarget($cur_answer)
        ->setAction(PonderConstants::ANSWERED_LITERAL)
        ->setHandles($handles)
        ->setUser($user);

      $commentview = new PonderCommentListView();
      $commentview
        ->setUser($user)
        ->setHandles($handles)
        ->setComments($cur_answer->getComments())
        ->setTarget($cur_answer->getPHID())
        ->setQuestionID($question->getID())
        ->setActionURI(new PhutilURI('/ponder/comment/add/'));

      $panel->appendChild($view);
      $panel->appendChild($commentview);
      $panel->appendChild(
        hsprintf('<div style="height: 40px; clear : both"></div>'));

    }

    return $panel->render();
  }
}
