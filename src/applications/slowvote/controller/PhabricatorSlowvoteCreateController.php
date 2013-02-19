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
      $poll->setShuffle((int)$request->getBool('shuffle', false));
      $poll->setMethod($request->getInt('method'));

      if (!strlen($poll->getQuestion())) {
        $e_question = pht('Required');
        $errors[] = pht('You must ask a poll question.');
      } else {
        $e_question = null;
      }

      $responses = array_filter($responses);
      if (empty($responses)) {
        $errors[] = pht('You must offer at least one response.');
        $e_response = pht('Required');
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
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    }

    $instructions =
      phutil_tag(
        'p',
        array(
          'class' => 'aphront-form-instructions',
        ),
        pht('Resolve issues and build consensus through '.
          'protracted deliberation.'));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild($instructions)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Question'))
          ->setName('question')
          ->setValue($poll->getQuestion())
          ->setError($e_question));

    for ($ii = 0; $ii < 10; $ii++) {
      $n = ($ii + 1);
      $response = id(new AphrontFormTextControl())
        ->setLabel(pht("Response %d", $n))
        ->setName('response[]')
        ->setValue(idx($responses, $ii, ''));

      if ($ii == 0) {
        $response->setError($e_response);
      }

      $form->appendChild($response);
    }

    $poll_type_options = array(
      PhabricatorSlowvotePoll::METHOD_PLURALITY =>
        pht('Plurality (Single Choice)'),
      PhabricatorSlowvotePoll::METHOD_APPROVAL  =>
        pht('Approval (Multiple Choice)'),
    );

    $response_type_options = array(
      PhabricatorSlowvotePoll::RESPONSES_VISIBLE
        => pht('Allow anyone to see the responses'),
      PhabricatorSlowvotePoll::RESPONSES_VOTERS
        => pht('Require a vote to see the responses'),
      PhabricatorSlowvotePoll::RESPONSES_OWNER
        => pht('Only I can see the responses'),
    );

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Vote Type'))
          ->setName('method')
          ->setValue($poll->getMethod())
          ->setOptions($poll_type_options))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Responses'))
          ->setName('response_visibility')
          ->setValue($poll->getResponseVisibility())
          ->setOptions($response_type_options))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel(pht('Shuffle'))
          ->addCheckbox(
            'shuffle',
            1,
            pht('Show choices in random order'),
            $poll->getShuffle()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Create Slowvote'))
          ->addCancelButton('/vote/'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader(pht('Create Slowvote'));
    $panel->setNoBackground();
    $panel->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Create Slowvote'))
        ->setHref($this->getApplicationURI().'create/'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $error_view,
        $panel,
      ),
      array(
        'title' => pht('Create Slowvote'),
        'device' => true,
      ));
  }

}
