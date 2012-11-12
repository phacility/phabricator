<?php

final class PonderQuestionAskController extends PonderController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $question = id(new PonderQuestion())
      ->setAuthorPHID($user->getPHID())
      ->setVoteCount(0)
      ->setAnswerCount(0)
      ->setHeat(0.0);

    $errors = array();
    $e_title = true;
    if ($request->isFormPost()) {
      $question->setTitle($request->getStr('title'));
      $question->setContent($request->getStr('content'));

      $len = phutil_utf8_strlen($question->getTitle());
      if ($len < 1) {
        $errors[] = pht('Title must not be empty.');
        $e_title = pht('Required');
      } else if ($len > 255) {
        $errors[] = pht('Title is too long.');
        $e_title = pht('Too Long');
      }

      if (!$errors) {
        $content_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_WEB,
          array(
            'ip' => $request->getRemoteAddr(),
          ));
        $question->setContentSource($content_source);

        id(new PonderQuestionEditor())
          ->setQuestion($question)
          ->setActor($user)
          ->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/Q'.$question->getID());
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $header = id(new PhabricatorHeaderView())->setHeader(pht('Ask Question'));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Question'))
          ->setName('title')
          ->setValue($question->getTitle())
          ->setError($e_title))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('content')
          ->setID('content')
          ->setValue($question->getContent())
          ->setLabel(pht('Description')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Ask Away!'));

    $preview =
      '<div class="aphront-panel-flush">'.
        '<div id="question-preview">'.
          '<span class="aphront-panel-preview-loading-text">'.
            pht('Loading question preview...').
          '</span>'.
        '</div>'.
      '</div>';

    Javelin::initBehavior(
      'ponder-feedback-preview',
      array(
        'uri'         => '/ponder/question/preview/',
        'content'     => 'content',
        'preview'     => 'question-preview',
        'question_id' => null
      ));

    $nav = $this->buildSideNavView($question);
    $nav->selectFilter($question->getID() ? null : 'question/ask');

    $nav->appendChild(
      array(
        $header,
        $error_view,
        $form,
        $preview,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'device' => true,
        'title'  => 'Ask a Question',
      )
    );
  }

}
