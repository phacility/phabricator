<?php

final class PhabricatorPeopleInviteSendController
  extends PhabricatorPeopleInviteController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      PeopleCreateUsersCapability::CAPABILITY);

    $is_confirm = false;
    $errors = array();
    $confirm_errors = array();
    $e_emails = true;

    $message = $request->getStr('message');
    $emails = $request->getStr('emails');
    $severity = PHUIErrorView::SEVERITY_ERROR;
    if ($request->isFormPost()) {
      // NOTE: We aren't using spaces as a delimiter here because email
      // addresses with names often include spaces.
      $email_list = preg_split('/[,;\n]+/', $emails);
      foreach ($email_list as $key => $email) {
        if (!strlen(trim($email))) {
          unset($email_list[$key]);
        }
      }

      if ($email_list) {
        $e_emails = null;
      } else {
        $e_emails = pht('Required');
        $errors[] = pht(
          'To send invites, you must enter at least one email address.');
      }

      if (!$errors) {
        $is_confirm = true;

        $actions = PhabricatorAuthInviteAction::newActionListFromAddresses(
          $viewer,
          $email_list);

        $any_valid = false;
        $all_valid = true;
        $action_send = PhabricatorAuthInviteAction::ACTION_SEND;
        foreach ($actions as $action) {
          if ($action->getAction() == $action_send) {
            $any_valid = true;
          } else {
            $all_valid = false;
          }
        }

        if (!$any_valid) {
          $confirm_errors[] = pht(
            'None of the provided addresses are valid invite recipients. '.
            'Review the table below for details. Revise the address list '.
            'to continue.');
        } else if ($all_valid) {
          $confirm_errors[] = pht(
            'All of the addresses appear to be valid invite recipients. '.
            'Confirm the actions below to continue.');
          $severity = PHUIErrorView::SEVERITY_NOTICE;
        } else {
          $confirm_errors[] = pht(
            'Some of the addresses you entered do not appear to be '.
            'valid recipients. Review the table below. You can revise '.
            'the address list, or ignore these errors and continue.');
          $severity = PHUIErrorView::SEVERITY_WARNING;
        }

        if ($any_valid && $request->getBool('confirm')) {
          throw new Exception(
            pht('TODO: This workflow is not yet fully implemented.'));
        }
      }
    }

    if ($is_confirm) {
      $title = pht('Confirm Invites');
    } else {
      $title = pht('Invite Users');
    }

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_confirm) {
      $crumbs->addTextCrumb(pht('Confirm'));
    } else {
      $crumbs->addTextCrumb(pht('Invite Users'));
    }

    $confirm_box = null;
    if ($is_confirm) {

      $handles = array();
      if ($actions) {
        $handles = $this->loadViewerHandles(mpull($actions, 'getUserPHID'));
      }

      $invite_table = id(new PhabricatorAuthInviteActionTableView())
        ->setUser($viewer)
        ->setInviteActions($actions)
        ->setHandles($handles);

      $confirm_form = null;
      if ($any_valid) {
        $confirm_form = id(new AphrontFormView())
          ->setUser($viewer)
          ->addHiddenInput('message', $message)
          ->addHiddenInput('emails', $emails)
          ->addHiddenInput('confirm', true)
          ->appendRemarkupInstructions(
            pht(
              'If everything looks good, click **Send Invitations** to '.
              'deliver email invitations these users. Otherwise, edit the '.
              'email list or personal message at the bottom of the page to '.
              'revise the invitations.'))
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Send Invitations')));
      }

      $confirm_box = id(new PHUIObjectBoxView())
        ->setErrorView(
          id(new PHUIErrorView())
            ->setErrors($confirm_errors)
            ->setSeverity($severity))
        ->setHeaderText(pht('Confirm Invites'))
        ->appendChild($invite_table)
        ->appendChild($confirm_form);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          'To invite users to Phabricator, enter their email addresses below. '.
          'Separate addresses with commas or newlines.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Email Addresses'))
          ->setName(pht('emails'))
          ->setValue($emails)
          ->setError($e_emails)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL))
      ->appendRemarkupInstructions(
        pht(
          'You can optionally include a heartfelt personal message in '.
          'the email.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Message'))
          ->setName(pht('message'))
          ->setValue($message)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(
            $is_confirm
              ? pht('Update Preview')
              : pht('Continue'))
          ->addCancelButton($this->getApplicationURI('invite/')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(
        $is_confirm
          ? pht('Revise Invites')
          : pht('Invite Users'))
      ->setFormErrors($errors)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $confirm_box,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
