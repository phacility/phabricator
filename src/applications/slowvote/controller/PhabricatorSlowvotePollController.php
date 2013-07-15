<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvotePollController
  extends PhabricatorSlowvoteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $viewer_phid = $user->getPHID();

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$poll) {
      return new Aphront404Response();
    }

    $options = id(new PhabricatorSlowvoteOption())->loadAllWhere(
      'pollID = %d',
      $poll->getID());
    $choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
      'pollID = %d',
      $poll->getID());

    $choices_by_option = mgroup($choices, 'getOptionID');
    $choices_by_user = mgroup($choices, 'getAuthorPHID');
    $viewer_choices = idx($choices_by_user, $viewer_phid, array());

    if ($request->isAjax()) {
      $embed = id(new SlowvoteEmbedView())
        ->setPoll($poll)
        ->setOptions($options)
        ->setViewerChoices($viewer_choices);

      return id(new AphrontAjaxResponse())
        ->setContent(array(
          'pollID' => $poll->getID(),
          'contentHTML' => $embed->render()));
    }

    require_celerity_resource('phabricator-slowvote-css');

    $phids = array_merge(
      mpull($choices, 'getAuthorPHID'),
      array(
        $poll->getAuthorPHID(),
      ));

    $query = new PhabricatorObjectHandleData($phids);
    $query->setViewer($user);
    $handles = $query->loadHandles();
    $objects = $query->loadObjects();

    if ($poll->getShuffle()) {
      shuffle($options);
    }

    $option_markup = array();
    foreach ($options as $option) {
      $option_markup[] = $this->renderPollOption(
        $poll,
        $viewer_choices,
        $option);
    }

    switch ($poll->getMethod()) {
      case PhabricatorSlowvotePoll::METHOD_PLURALITY:
        $choice_ids = array();
        foreach ($choices_by_user as $user_phid => $user_choices) {
          $choice_ids[$user_phid] = head($user_choices)->getOptionID();
        }
        break;
      case PhabricatorSlowvotePoll::METHOD_APPROVAL:
        break;
      default:
        throw new Exception("Unknown poll method!");
    }

    $result_markup = $this->renderResultMarkup(
      $poll,
      $options,
      $choices,
      $viewer_choices,
      $choices_by_option,
      $handles,
      $objects);

    if ($viewer_choices) {
      $instructions =
        pht('Your vote has been recorded... but there is still ample time to '.
        'rethink your position. Have you thoroughly considered all possible '.
        'eventualities?');
    } else {
      $instructions =
        pht('This is a weighty matter indeed. Consider your choices with the '.
        'greatest of care.');
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->setAction(sprintf('/vote/%d/', $poll->getID()))
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        $instructions))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Vote'))
          ->setValue($option_markup))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Engage in Deliberations')));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($poll->getQuestion());

    $actions = $this->buildActionView($poll);
    $properties = $this->buildPropertyView($poll);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('V'.$poll->getID()));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($result_markup);

    $content = array(
      $form,
      hsprintf('<br /><br />'),
      $panel);

    $xactions = $this->buildTransactions($poll);
    $add_comment = $this->buildCommentForm($poll);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $content,
        $xactions,
        $add_comment,
      ),
      array(
        'title' => 'V'.$poll->getID().' '.$poll->getQuestion(),
        'device' => true,
        'dust' => true,
      ));
  }


  private function renderPollOption(
    PhabricatorSlowvotePoll $poll,
    array $viewer_choices,
    PhabricatorSlowvoteOption $option) {
    assert_instances_of($viewer_choices, 'PhabricatorSlowvoteChoice');

    $id = $option->getID();
    switch ($poll->getMethod()) {
      case PhabricatorSlowvotePoll::METHOD_PLURALITY:

        // Render a radio button.

        $selected_option = head($viewer_choices);
        if ($selected_option) {
          $selected = $selected_option->getOptionID();
        } else {
          $selected = null;
        }

        if ($selected == $id) {
          $checked = "checked";
        } else {
          $checked = null;
        }

        $input = phutil_tag(
          'input',
          array(
            'type'      => 'radio',
            'name'      => 'vote[]',
            'value'     => $id,
            'checked'   => $checked,
          ));
        break;
      case PhabricatorSlowvotePoll::METHOD_APPROVAL:

        // Render a check box.

        $checked = null;
        foreach ($viewer_choices as $choice) {
          if ($choice->getOptionID() == $id) {
            $checked = 'checked';
            break;
          }
        }

        $input = phutil_tag(
          'input',
          array(
            'type'    => 'checkbox',
            'name'    => 'vote[]',
            'checked' => $checked,
            'value'   => $id,
          ));
        break;
      default:
        throw new Exception("Unknown poll method!");
    }

    if ($checked) {
      $checked_class = 'phabricator-slowvote-checked';
    } else {
      $checked_class = null;
    }

    return phutil_tag(
      'label',
      array(
        'class' => 'phabricator-slowvote-label '.$checked_class,
      ),
      array($input, $option->getName()));
  }

  private function renderVoteCount(
    PhabricatorSlowvotePoll $poll,
    array $choices,
    array $chosen) {
    assert_instances_of($choices, 'PhabricatorSlowvoteChoice');
    assert_instances_of($chosen, 'PhabricatorSlowvoteChoice');

    switch ($poll->getMethod()) {
      case PhabricatorSlowvotePoll::METHOD_PLURALITY:
        $out_of_total = count($choices);
        break;
      case PhabricatorSlowvotePoll::METHOD_APPROVAL:
        // Count unique respondents for approval votes.
        $out_of_total = count(mpull($choices, null, 'getAuthorPHID'));
        break;
      default:
        throw new Exception("Unknown poll method!");
    }

    return sprintf(
      '%d / %d (%d%%)',
      number_format(count($chosen)),
      number_format($out_of_total),
      $out_of_total
        ? round(100 * count($chosen) / $out_of_total)
        : 0);
  }

  private function renderResultMarkup(
    PhabricatorSlowvotePoll $poll,
    array $options,
    array $choices,
    array $viewer_choices,
    array $choices_by_option,
    array $handles,
    array $objects) {
    assert_instances_of($options, 'PhabricatorSlowvoteOption');
    assert_instances_of($choices, 'PhabricatorSlowvoteChoice');
    assert_instances_of($viewer_choices, 'PhabricatorSlowvoteChoice');
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    assert_instances_of($objects, 'PhabricatorLiskDAO');

    $viewer_phid = $this->getRequest()->getUser()->getPHID();

    $can_see_responses = false;
    $need_vote = false;
    switch ($poll->getResponseVisibility()) {
      case PhabricatorSlowvotePoll::RESPONSES_VISIBLE:
        $can_see_responses = true;
        break;
      case PhabricatorSlowvotePoll::RESPONSES_VOTERS:
        $can_see_responses = (bool)$viewer_choices;
        $need_vote = true;
        break;
      case PhabricatorSlowvotePoll::RESPONSES_OWNER:
        $can_see_responses = ($viewer_phid == $poll->getAuthorPHID());
        break;
    }

    $result_markup = id(new AphrontFormLayoutView())
      ->appendChild(phutil_tag('h1', array(), pht('Ongoing Deliberation')));

    if (!$can_see_responses) {
      if ($need_vote) {
        $reason = pht("You must vote to see the results.");
      } else {
        $reason = pht("The results are not public.");
      }
      $result_markup
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions"><em>%s</em></p>',
          $reason));
      return $result_markup;
    }

    foreach ($options as $option) {
      $id = $option->getID();

      $chosen = idx($choices_by_option, $id, array());
      $users = array_select_keys($handles, mpull($chosen, 'getAuthorPHID'));
      if ($users) {
        $user_markup = array();
        foreach ($users as $handle) {
          $object = idx($objects, $handle->getPHID());
          if (!$object) {
            continue;
          }

          $profile_image = $handle->getImageURI();

          $user_markup[] = phutil_tag(
            'a',
            array(
              'href'  => $handle->getURI(),
              'class' => 'phabricator-slowvote-facepile',
            ),
            phutil_tag(
              'img',
              array(
                'src' => $profile_image,
              )));
        }
      } else {
        $user_markup = pht('This option has failed to appeal to anyone.');
      }

      $vote_count = $this->renderVoteCount(
        $poll,
        $choices,
        $chosen);

      $result_markup->appendChild(hsprintf(
        '<div>'.
          '<div class="phabricator-slowvote-count">%s</div>'.
          '<h1>%s</h1>'.
          '<hr class="phabricator-slowvote-hr" />'.
          '%s'.
          '<div style="clear: both;"></div>'.
          '<hr class="phabricator-slowvote-hr" />'.
        '</div>',
        $vote_count,
        $option->getName(),
        phutil_tag('div', array(), $user_markup)));
    }

    return $result_markup;
  }

  private function buildActionView(PhabricatorSlowvotePoll $poll) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($poll);

    return $view;
  }

  private function buildPropertyView(PhabricatorSlowvotePoll $poll) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($poll);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $poll);

    $view->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $view->invokeWillRenderEvent();

    if (strlen($poll->getDescription())) {
      $view->addTextSection(
        $output = PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent(
            $poll->getDescription()),
          'default',
          $viewer));
    }

    return $view;
  }

  private function buildTransactions(PhabricatorSlowvotePoll $poll) {
    $viewer = $this->getRequest()->getUser();

    $xactions = id(new PhabricatorSlowvoteTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($poll->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    return $timeline;
  }

  private function buildCommentForm(PhabricatorSlowvotePoll $poll) {
    $viewer = $this->getRequest()->getUser();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = id(new PhabricatorHeaderView())
      ->setHeader(
        $is_serious
          ? pht('Add Comment')
          : pht('Enter Deliberations'));

    $submit_button_name = $is_serious
      ? pht('Add Comment')
      : pht('Perhaps');

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $poll->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setDraft($draft)
      ->setAction($this->getApplicationURI('/comment/'.$poll->getID().'/'))
      ->setSubmitButtonName($submit_button_name);

    return array(
      $add_comment_header,
      $add_comment_form,
    );
  }


}
