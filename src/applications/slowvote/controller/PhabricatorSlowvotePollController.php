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

    if ($request->isFormPost()) {
      $comment = idx($comments_by_user, $viewer_phid, null);
      if ($comment) {
        $comment->delete();
      }

      $comment_text = $request->getStr('comments');
      if (strlen($comment_text)) {
        id(new PhabricatorSlowvoteComment())
          ->setAuthorPHID($viewer_phid)
          ->setPollID($poll->getID())
          ->setCommentText($comment_text)
          ->save();
      }

      $votes = $request->getArr('vote');

      switch ($poll->getMethod()) {
        case PhabricatorSlowvotePoll::METHOD_PLURALITY:
          // Enforce only one vote.
          $votes = array_slice($votes, 0, 1);
          break;
        case PhabricatorSlowvotePoll::METHOD_APPROVAL:
          // No filtering.
          break;
        default:
          throw new Exception("Unknown poll method!");
      }

      foreach ($viewer_choices as $viewer_choice) {
        $viewer_choice->delete();
      }

      foreach ($votes as $vote) {
        id(new PhabricatorSlowvoteChoice())
          ->setAuthorPHID($viewer_phid)
          ->setPollID($poll->getID())
          ->setOptionID($vote)
          ->save();
      }

      return id(new AphrontRedirectResponse())->setURI('/V'.$poll->getID());
    }

    require_celerity_resource('phabricator-slowvote-css');

    $phids = array_merge(
      mpull($choices, 'getAuthorPHID'),
      mpull($comments, 'getAuthorPHID'),
      array(
        $poll->getAuthorPHID(),
      ));

    $query = new PhabricatorObjectHandleData($phids);
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
    $option_markup = implode("\n", $option_markup);

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
        'Your vote has been recorded... but there is still ample time to '.
        'rethink your position. Have you thoroughly considered all possible '.
        'eventualities?';
    } else {
      $instructions =
        'This is a weighty matter indeed. Consider your choices with the '.
        'greatest of care.';
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        '<p class="aphront-form-instructions">'.$instructions.'</p>')
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Vote')
          ->setValue($option_markup))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Comments')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setName('comments')
          ->setValue($comment_text))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Cautiously Engage in Deliberations'));


    $panel = new AphrontPanelView();
    $panel->setHeader(phutil_escape_html($poll->getQuestion()));
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    $panel->appendChild($form);
    $panel->appendChild('<br /><br />');
    $panel->appendChild($result_markup);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'V'.$poll->getID().' '.$poll->getQuestion(),
      ));
  }

  private function renderComments(array $comments, array $handles) {
    assert_instances_of($comments, 'PhabricatorSlowvoteComment');
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    $viewer = $this->getRequest()->getUser();

    $engine = PhabricatorMarkupEngine::newSlowvoteMarkupEngine();

    $comment_markup = array();
    foreach ($comments as $comment) {
      $handle = $handles[$comment->getAuthorPHID()];

      $markup = $engine->markupText($comment->getCommentText());

      require_celerity_resource('phabricator-remarkup-css');

      $comment_markup[] =
        '<tr>'.
          '<th>'.
            $handle->renderLink().
            '<div class="phabricator-slowvote-datestamp">'.
              phabricator_datetime($comment->getDateCreated(), $viewer).
            '</div>'.
          '<td>'.
            '<div class="phabricator-remarkup">'.
              $markup.
            '</div>'.
          '</td>'.
        '</tr>';
    }

    if ($comment_markup) {
      $comment_markup = phutil_render_tag(
        'table',
        array(
          'class' => 'phabricator-slowvote-comments',
        ),
        implode("\n", $comment_markup));
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

        $input = phutil_render_tag(
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

        $input = phutil_render_tag(
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

    return phutil_render_tag(
      'label',
      array(
        'class' => 'phabricator-slowvote-label '.$checked_class,
      ),
      $input.phutil_escape_html($option->getName()));
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
      ->appendChild('<h1>Ongoing Deliberation</h1>');

    if (!$can_see_responses) {
      if ($need_vote) {
        $reason = "You must vote to see the results.";
      } else {
        $reason = "The results are not public.";
      }
      $result_markup
        ->appendChild(
          '<p class="aphront-form-instructions"><em>'.$reason.'</em></p>');
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

          $user_markup[] = phutil_render_tag(
            'a',
            array(
              'href'  => $handle->getURI(),
              'class' => 'phabricator-slowvote-facepile',
            ),
            phutil_render_tag(
              'img',
              array(
                'src' => $profile_image,
              )));
        }
        $user_markup = implode('', $user_markup);
      } else {
        $user_markup = 'This option has failed to appeal to anyone.';
      }

      $comment_markup = $this->renderComments(
        idx($comments_by_option, $id, array()),
        $handles);

      $vote_count = $this->renderVoteCount(
        $poll,
        $choices,
        $chosen);

      $result_markup->appendChild(
        '<div>'.
          '<div class="phabricator-slowvote-count">'.
            $vote_count.
          '</div>'.
          '<h1>'.phutil_escape_html($option->getName()).'</h1>'.
          '<hr class="phabricator-slowvote-hr" />'.
          $user_markup.
          '<div style="clear: both;">'.
          '<hr class="phabricator-slowvote-hr" />'.
          $comment_markup.
        '</div>');
    }

    if ($poll->getMethod() == PhabricatorSlowvotePoll::METHOD_APPROVAL &&
        $comments) {
      $comment_markup = $this->renderComments(
        $comments,
        $handles);
      $result_markup->appendChild(
        '<h1>Motions Proposed for Consideration</h1>');
      $result_markup->appendChild($comment_markup);
    }

    return $result_markup;
  }
}
