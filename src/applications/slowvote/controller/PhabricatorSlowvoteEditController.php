<?php

final class PhabricatorSlowvoteEditController
  extends PhabricatorSlowvoteController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $poll = id(new PhabricatorSlowvoteQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$poll) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $poll = PhabricatorSlowvotePoll::initializeNewPoll($viewer);
      $is_new = true;
    }

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $poll->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    $e_question = true;
    $e_response = true;
    $errors = array();

    $v_question = $poll->getQuestion();
    $v_description = $poll->getDescription();
    $v_responses = $poll->getResponseVisibility();
    $v_shuffle = $poll->getShuffle();
    $v_space = $poll->getSpacePHID();

    $responses = $request->getArr('response');
    if ($request->isFormPost()) {
      $v_question = $request->getStr('question');
      $v_description = $request->getStr('description');
      $v_responses = $request->getStr('responses');
      $v_shuffle = (int)$request->getBool('shuffle');
      $v_view_policy = $request->getStr('viewPolicy');
      $v_projects = $request->getArr('projects');

      $v_space = $request->getStr('spacePHID');

      if ($is_new) {
        $poll->setMethod($request->getStr('method'));
      }

      if (!strlen($v_question)) {
        $e_question = pht('Required');
        $errors[] = pht('You must ask a poll question.');
      } else {
        $e_question = null;
      }

      if ($is_new) {
        // NOTE: Make sure common and useful response "0" is preserved.
        foreach ($responses as $key => $response) {
          if (!strlen($response)) {
            unset($responses[$key]);
          }
        }

        if (empty($responses)) {
          $errors[] = pht('You must offer at least one response.');
          $e_response = pht('Required');
        } else {
          $e_response = null;
        }
      }

      $template = id(new PhabricatorSlowvoteTransaction());
      $xactions = array();

      if ($is_new) {
        $xactions[] = id(new PhabricatorSlowvoteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_CREATE);
      }

      $xactions[] = id(clone $template)
        ->setTransactionType(
            PhabricatorSlowvoteQuestionTransaction::TRANSACTIONTYPE)
        ->setNewValue($v_question);

      $xactions[] = id(clone $template)
        ->setTransactionType(
            PhabricatorSlowvoteDescriptionTransaction::TRANSACTIONTYPE)
        ->setNewValue($v_description);

      $xactions[] = id(clone $template)
        ->setTransactionType(
            PhabricatorSlowvoteResponsesTransaction::TRANSACTIONTYPE)
        ->setNewValue($v_responses);

      $xactions[] = id(clone $template)
        ->setTransactionType(
            PhabricatorSlowvoteShuffleTransaction::TRANSACTIONTYPE)
        ->setNewValue($v_shuffle);

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($v_view_policy);

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_SPACE)
        ->setNewValue($v_space);

      if (empty($errors)) {
        $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $xactions[] = id(new PhabricatorSlowvoteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $proj_edge_type)
          ->setNewValue(array('=' => array_fuse($v_projects)));

        $editor = id(new PhabricatorSlowvoteEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);

        $xactions = $editor->applyTransactions($poll, $xactions);

        if ($is_new) {
          $poll->save();

          foreach ($responses as $response) {
            $option = new PhabricatorSlowvoteOption();
            $option->setName($response);
            $option->setPollID($poll->getID());
            $option->save();
          }
        }

        return id(new AphrontRedirectResponse())
          ->setURI($poll->getURI());
      } else {
        $poll->setViewPolicy($v_view_policy);
      }
    }

    $form = id(new AphrontFormView())
      ->setAction($request->getrequestURI())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Question'))
          ->setName('question')
          ->setValue($v_question)
          ->setError($e_question))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setLabel(pht('Description'))
          ->setName('description')
          ->setValue($v_description))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Tags'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()));

    if ($is_new) {
      for ($ii = 0; $ii < 10; $ii++) {
        $n = ($ii + 1);
        $response = id(new AphrontFormTextControl())
          ->setLabel(pht('Response %d', $n))
          ->setName('response[]')
          ->setValue(idx($responses, $ii, ''));

        if ($ii == 0) {
          $response->setError($e_response);
        }

        $form->appendChild($response);
      }
    }

    $vote_type_map = SlowvotePollVotingMethod::getAll();
    $vote_type_options = mpull($vote_type_map, 'getNameForEdit');

    $method = $poll->getMethod();
    if (!isset($vote_type_options[$method])) {
      $method_object =
        SlowvotePollVotingMethod::newVotingMethodObject(
          $method);

      $vote_type_options = array(
        $method => $method_object->getNameForEdit(),
      ) + $vote_type_options;
    }

    $response_type_map = SlowvotePollResponseVisibility::getAll();
    $response_type_options = mpull($response_type_map, 'getNameForEdit');

    $visibility = $poll->getResponseVisibility();
    if (!isset($response_type_options[$visibility])) {
      $visibility_object =
        SlowvotePollResponseVisibility::newResponseVisibilityObject(
          $visibility);

      $response_type_options = array(
        $visibility => $visibility_object->getNameForEdit(),
      ) + $response_type_options;
    }

    if ($is_new) {
      $form->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Vote Type'))
          ->setName('method')
          ->setValue($poll->getMethod())
          ->setOptions($vote_type_options));
    } else {
      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Vote Type'))
          ->setValue(idx($vote_type_options, $poll->getMethod())));
    }

    if ($is_new) {
      $title = pht('Create Slowvote');
      $button = pht('Create');
      $cancel_uri = $this->getApplicationURI();
      $header_icon = 'fa-plus-square';
    } else {
      $title = pht('Edit Poll: %s', $poll->getQuestion());
      $button = pht('Save Changes');
      $cancel_uri = '/V'.$poll->getID();
      $header_icon = 'fa-pencil';
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($poll)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Responses'))
          ->setName('responses')
          ->setValue($v_responses)
          ->setOptions($response_type_options))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel(pht('Shuffle'))
          ->addCheckbox(
            'shuffle',
            1,
            pht('Show choices in random order.'),
            $v_shuffle))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setName('viewPolicy')
          ->setPolicyObject($poll)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setSpacePHID($v_space))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button)
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setFooter($form_box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

}
