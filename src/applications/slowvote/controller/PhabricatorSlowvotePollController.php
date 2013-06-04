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

    $poll = id(new PhabricatorSlowvotePoll())->load($this->id);
    if (!$poll) {
      return new Aphront404Response();
    }

    $options = id(new PhabricatorSlowvoteOption())->loadAllWhere(
      'pollID = %d',
      $poll->getID());
    $choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
      'pollID = %d',
      $poll->getID());
    $comments = id(new PhabricatorSlowvoteComment())->loadAllWhere(
      'pollID = %d',
      $poll->getID());

    $choices_by_option = mgroup($choices, 'getOptionID');
    $comments_by_user = mpull($comments, null, 'getAuthorPHID');
    $choices_by_user = mgroup($choices, 'getAuthorPHID');
    $viewer_choices = idx($choices_by_user, $viewer_phid, array());
    $viewer_comment = idx($comments_by_user, $viewer_phid, null);

    $comment_text = null;
    if ($viewer_comment) {
      $comment_text = $viewer_comment->getCommentText();
    }

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
      mpull($comments, 'getAuthorPHID'),
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

    $comments_by_option = array();
    switch ($poll->getMethod()) {
      case PhabricatorSlowvotePoll::METHOD_PLURALITY:
        $choice_ids = array();
        foreach ($choices_by_user as $user_phid => $user_choices) {
          $choice_ids[$user_phid] = head($user_choices)->getOptionID();
        }
        foreach ($comments as $comment) {
          $choice = idx($choice_ids, $comment->getAuthorPHID());
          if ($choice) {
            $comments_by_option[$choice][] = $comment;
          }
        }
        break;
      case PhabricatorSlowvotePoll::METHOD_APPROVAL:
        // All comments are grouped in approval voting.
        break;
      default:
        throw new Exception("Unknown poll method!");
    }

    $result_markup = $this->renderResultMarkup(
      $poll,
      $options,
      $choices,
      $comments,
      $viewer_choices,
      $choices_by_option,
      $comments_by_option,
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
      ->setAction(sprintf('/vote/%d/', $poll->getID()))
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        $instructions))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Vote'))
          ->setValue($option_markup))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Comments'))
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setName('comments')
          ->setValue($comment_text))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Engage in Deliberations')));


    $panel = new AphrontPanelView();
    $panel->setHeader($poll->getQuestion());
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->setNoBackground();
    $panel->appendChild($form);
    $panel->appendChild(hsprintf('<br /><br />'));
    $panel->appendChild($result_markup);

    return $this->buildApplicationPage(
      $panel,
      array(
        'title' => 'V'.$poll->getID().' '.$poll->getQuestion(),
        'device' => true,
      ));
  }

  private function renderComments(array $comments, array $handles) {
    assert_instances_of($comments, 'PhabricatorSlowvoteComment');
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    $viewer = $this->getRequest()->getUser();

    $engine = PhabricatorMarkupEngine::newSlowvoteMarkupEngine();
    $engine->setConfig('viewer', $viewer);

    $comment_markup = array();
    foreach ($comments as $comment) {
      $handle = $handles[$comment->getAuthorPHID()];

      $markup = $engine->markupText($comment->getCommentText());

      require_celerity_resource('phabricator-remarkup-css');

      $comment_markup[] = hsprintf(
        '<tr>'.
          '<th>'.
            '%s'.
            '<div class="phabricator-slowvote-datestamp">%s</div>'.
          '</th>'.
          '<td>'.
            '<div class="phabricator-remarkup">%s</div>'.
          '</td>'.
        '</tr>',
        $handle->renderLink(),
        phabricator_datetime($comment->getDateCreated(), $viewer),
        $markup);
    }

    if ($comment_markup) {
      $comment_markup = phutil_tag(
        'table',
        array(
          'class' => 'phabricator-slowvote-comments',
        ),
        $comment_markup);
    } else {
      $comment_markup = null;
    }

    return $comment_markup;
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
    array $comments,
    array $viewer_choices,
    array $choices_by_option,
    array $comments_by_option,
    array $handles,
    array $objects) {
    assert_instances_of($options, 'PhabricatorSlowvoteOption');
    assert_instances_of($choices, 'PhabricatorSlowvoteChoice');
    assert_instances_of($comments, 'PhabricatorSlowvoteComment');
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

      $comment_markup = $this->renderComments(
        idx($comments_by_option, $id, array()),
        $handles);

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
          '<div style="clear: both;" />'.
          '<hr class="phabricator-slowvote-hr" />'.
          '%s'.
        '</div>',
        $vote_count,
        $option->getName(),
        phutil_tag('div', array(), $user_markup),
        $comment_markup));
    }

    if ($poll->getMethod() == PhabricatorSlowvotePoll::METHOD_APPROVAL &&
        $comments) {
      $comment_markup = $this->renderComments(
        $comments,
        $handles);
      $result_markup->appendChild(
        phutil_tag('h1', array(), pht('Motions Proposed for Consideration')));
      $result_markup->appendChild($comment_markup);
    }

    return $result_markup;
  }
}
