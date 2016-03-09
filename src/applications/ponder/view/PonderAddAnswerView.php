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
    $viewer = $this->getViewer();

    $authors = mpull($question->getAnswers(), null, 'getAuthorPHID');
    if (isset($authors[$viewer->getPHID()])) {
      $view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setTitle(pht('Already Answered'))
        ->appendChild(
          pht(
            'You have already answered this question. You can not answer '.
            'twice, but you can edit your existing answer.'));
      return phutil_tag_div('ponder-add-answer-view', $view);
    }

    $info_panel = null;
    if ($question->getStatus() != PonderQuestionStatus::STATUS_OPEN) {
      $info_panel = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(
          pht(
            'This question has been marked as closed,
             but you can still leave a new answer.'));
    }

    $box_style = null;
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('New Answer'))
      ->addClass('ponder-add-answer-header');

    $form = new AphrontFormView();
    $form
      ->setViewer($viewer)
      ->setAction($this->actionURI)
      ->setWorkflow(true)
      ->addHiddenInput('question_id', $question->getID())
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('answer')
          ->setLabel(pht('Answer'))
          ->setError(true)
          ->setID('answer-content')
          ->setViewer($viewer))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Add Answer')));

    if (!$viewer->isLoggedIn()) {
      $login_href = id(new PhutilURI('/auth/start/'))
          ->setQueryParam('next', '/Q'.$question->getID());
      $form = id(new PHUIFormLayoutView())
        ->addClass('login-to-participate')
        ->appendChild(
          id(new PHUIButtonView())
          ->setTag('a')
          ->setText(pht('Login to Answer'))
          ->setHref((string)$login_href));
    }

    $box = id(new PHUIObjectBoxView())
      ->appendChild($form)
      ->setHeaderText('Answer')
      ->addClass('ponder-add-answer-view');

    if ($info_panel) {
      $box->setInfoView($info_panel);
    }

    return array($header, $box);
  }
}
