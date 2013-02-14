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
    require_celerity_resource('ponder-core-view-css');

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $question = $this->question;

    $header = id(new PhabricatorHeaderView())
      ->setHeader('Add Answer');

    $form = new AphrontFormView();
    $form
      ->setFlexible(true)
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->setWorkflow(true)
      ->addHiddenInput('question_id', $question->getID())
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('answer')
          ->setLabel('Answer')
          ->setError(true)
          ->setID('answer-content')
          ->setUser($this->user))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Make it so'));

    $preview = hsprintf(
      '<div class="aphront-panel-flush">'.
        '<div id="answer-preview">'.
          '<span class="aphront-panel-preview-loading-text">'.
            'Loading answer preview...'.
          '</span>'.
        '</div>'.
      '</div>');

    Javelin::initBehavior(
      'ponder-feedback-preview',
      array(
        'uri'         => '/ponder/answer/preview/',
        'content'     => 'answer-content',
        'preview'     => 'answer-preview',
        'question_id' => $question->getID()
      ));

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $header,
          $form,
          $preview,
        ))
      ->render();
  }
}
