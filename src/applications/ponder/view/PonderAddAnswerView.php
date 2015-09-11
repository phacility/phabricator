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
    $viewer = $this->user;

    $authors = mpull($question->getAnswers(), null, 'getAuthorPHID');
    if (isset($authors[$viewer->getPHID()])) {
      return id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setTitle(pht('Already Answered'))
        ->appendChild(
          pht(
            'You have already answered this question. You can not answer '.
            'twice, but you can edit your existing answer.'));
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
    $own_question = null;
    $hide_action_id = celerity_generate_unique_node_id();
    $show_action_id = celerity_generate_unique_node_id();
    if ($question->getAuthorPHID() == $viewer->getPHID()) {
      $box_style = 'display: none;';
      $open_link = javelin_tag(
        'a',
        array(
          'sigil' => 'reveal-content',
          'class' => 'mml',
          'id' => $hide_action_id,
          'href' => '#',
          'meta' => array(
            'showIDs' => array($show_action_id),
            'hideIDs' => array($hide_action_id),
          ),
        ),
        pht('Add an answer.'));
      $own_question = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setID($hide_action_id)
        ->appendChild(
          pht(
            'This is your own question. You are welcome to provide
            an answer if you have found a resolution.'))
        ->appendChild($open_link);
    }

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
      ->setHeader($header)
      ->appendChild($form);

    if ($info_panel) {
      $box->setInfoView($info_panel);
    }

    $box = phutil_tag(
      'div',
      array(
        'style' => $box_style,
        'class' => 'mlt',
        'id' => $show_action_id,
      ),
      $box);

    return array($own_question, $box);
  }
}
