<?php

final class PonderQuestionDetailView extends AphrontView {

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
    require_celerity_resource('ponder-core-view-css');

    $question = $this->question;
    $handles = $this->handles;
    $user = $this->user;

    $panel = id(new AphrontPanelView())
      ->addClass("ponder-panel");

    $contentview = new PonderPostBodyView();
    $contentview
      ->setTarget($question)
      ->setQuestion($question)
      ->setUser($user)
      ->setHandles($handles)
      ->setAction(PonderConstants::ASKED_LITERAL);

    $commentview = new PonderCommentListView();
    $commentview
      ->setUser($user)
      ->setHandles($handles)
      ->setComments($question->getComments())
      ->setTarget($question->getPHID())
      ->setQuestionID($question->getID())
      ->setActionURI(new PhutilURI('/ponder/comment/add/'));

    $panel->appendChild($contentview);
    $panel->appendChild($commentview);

    return $panel->render();
  }

}
