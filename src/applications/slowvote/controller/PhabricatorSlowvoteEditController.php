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
      $v_responses = (int)$request->getInt('responses');
      $v_shuffle = (int)$request->getBool('shuffle');
      $v_view_policy = $request->getStr('viewPolicy');
      $v_projects = $request->getArr('projects');

      $v_space = $request->getStr('spacePHID');

      if ($is_new) {
        $poll->setMethod($request->getInt('method'));
      }

      if (!strlen($v_question)) {
        $e_question = pht('Required');
        $errors[] = pht('You must ask a poll question.');
      } else {
        $e_question = null;
      }

      if ($is_new) {
        $responses = array_filter($responses);
        if (empty($responses)) {
          $errors[] = pht('You must offer at least one response.');
          $e_response = pht('Required');
        } else {
          $e_response = null;
        }
      }

      $xactions = array();
      $template = id(new PhabricatorSlowvoteTransaction());

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorSlowvoteTransaction::TYPE_QUESTION)
        ->setNewValue($v_question);

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION)
        ->setNewValue($v_description);

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorSlowvoteTransaction::TYPE_RESPONSES)
        ->setNewValue($v_responses);

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorSlowvoteTransaction::TYPE_SHUFFLE)
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
          ->setURI('/V'.$poll->getID());
      } else {
        $poll->setViewPolicy($v_view_policy);
      }
    }

    $form = id(new AphrontFormView())
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

    if ($is_new) {
      $form->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Vote Type'))
          ->setName('method')
          ->setValue($poll->getMethod())
          ->setOptions($poll_type_options));
    } else {
      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Vote Type'))
          ->setValue(idx($poll_type_options, $poll->getMethod())));
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
      ->setHeaderText(pht('Poll'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
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
