<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvoteCreateController
  extends PhabricatorSlowvoteController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $poll = new PhabricatorSlowvotePoll();
    $poll->setAuthorPHID($user->getPHID());

    $e_question = true;
    $e_response = true;
    $errors = array();

    $responses = $request->getArr('response');

    if ($request->isFormPost()) {
      $poll->setQuestion($request->getStr('question'));
      $poll->setResponseVisibility($request->getInt('response_visibility'));
      $poll->setShuffle($request->getBool('shuffle', false));
      $poll->setMethod($request->getInt('method'));

      if (!strlen($poll->getQuestion())) {
        $e_question = 'Required';
        $errors[] = 'You must ask a poll question.';
      } else {
        $e_question = null;
      }

      $responses = array_filter($responses);
      if (empty($responses)) {
        $errors[] = 'You must offer at least one response.';
        $e_response = 'Required';
      } else {
        $e_response = null;
      }

      if (empty($errors)) {
        $poll->save();

        foreach ($responses as $response) {
          $option = new PhabricatorSlowvoteOption();
          $option->setName($response);
          $option->setPollID($poll->getID());
          $option->save();
        }

        return id(new AphrontRedirectResponse())
          ->setURI('/V'.$poll->getID());
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        '<p class="aphront-form-instructions">Resolve issues and build '.
        'consensus through protracted deliberation.</p>')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Question')
          ->setName('question')
          ->setValue($poll->getQuestion())
          ->setError($e_question));

    for ($ii = 0; $ii < 10; $ii++) {
      $n = ($ii + 1);
      $response = id(new AphrontFormTextControl())
        ->setLabel("Response {$n}")
        ->setName('response[]')
        ->setValue(idx($responses, $ii, ''));

      if ($ii == 0) {
        $response->setError($e_response);
      }

      $form->appendChild($response);
    }

    $poll_type_options = array(
      PhabricatorSlowvotePoll::METHOD_PLURALITY => 'Plurality (Single Choice)',
      PhabricatorSlowvotePoll::METHOD_APPROVAL  => 'Approval (Multiple Choice)',
    );

    $response_type_options = array(
      PhabricatorSlowvotePoll::RESPONSES_VISIBLE
        => 'Allow anyone to see the responses',
      PhabricatorSlowvotePoll::RESPONSES_VOTERS
        => 'Require a vote to see the responses',
      PhabricatorSlowvotePoll::RESPONSES_OWNER
        => 'Only I can see the responses',
    );

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Vote Type')
          ->setName('method')
          ->setValue($poll->getMethod())
          ->setOptions($poll_type_options))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Responses')
          ->setName('response_visibility')
          ->setValue($poll->getResponseVisibility())
          ->setOptions($response_type_options))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Shuffle')
          ->addCheckbox(
            'shuffle',
            1,
            'Show choices in random order',
            $poll->getShuffle()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Create Slowvote')
          ->addCancelButton('/vote/'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader('Create Slowvote');
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Create Slowvote',
      ));
  }

}
