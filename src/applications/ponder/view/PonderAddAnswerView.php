<?php

final class PonderAddAnswerView extends AphrontView {

  private $question;
  private $actionURI;
  private $draft;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
    return $this;
  }

  public function render() {
    $question = $this->question;

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Add Answer'));

    $form = new AphrontFormView();
    $form
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->setWorkflow(true)
      ->addHiddenInput('question_id', $question->getID())
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('answer')
          ->setLabel(pht('Answer'))
          ->setError(true)
          ->setID('answer-content')
          ->setUser($this->user))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Add Answer')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($form);
  }
}
