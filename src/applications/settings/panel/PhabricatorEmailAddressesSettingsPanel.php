<?php

final class PhabricatorEmailAddressesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'email';
  }

  public function getPanelName() {
    return pht('Email Addresses');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsEmailPanelGroup::PANELGROUPKEY;
  }

  public function isEditableByAdministrators() {
    if ($this->getUser()->getIsMailingList()) {
      return true;
    }

    return false;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $this->getUser();
    $editable = PhabricatorEnv::getEnvConfig('account.editable');

    $uri = $request->getRequestURI();
    $uri->setQueryParams(array());

    if ($editable) {
      $new = $request->getStr('new');
      if ($new) {
        return $this->returnNewAddressResponse($request, $uri, $new);
      }

      $delete = $request->getInt('delete');
      if ($delete) {
        return $this->returnDeleteAddressResponse($request, $uri, $delete);
      }
    }

    $verify = $request->getInt('verify');
    if ($verify) {
      return $this->returnVerifyAddressResponse($request, $uri, $verify);
    }

    $primary = $request->getInt('primary');
    if ($primary) {
      return $this->returnPrimaryAddressResponse($request, $uri, $primary);
    }

    $emails = id(new PhabricatorUserEmail())->loadAllWhere(
      'userPHID = %s ORDER BY address',
      $user->getPHID());

    $rowc = array();
    $rows = array();
    foreach ($emails as $email) {

      $button_verify = javelin_tag(
        'a',
        array(
          'class' => 'button small grey',
          'href'  => $uri->alter('verify', $email->getID()),
          'sigil' => 'workflow',
        ),
        pht('Verify'));

      $button_make_primary = javelin_tag(
        'a',
        array(
          'class' => 'button small grey',
          'href'  => $uri->alter('primary', $email->getID()),
          'sigil' => 'workflow',
        ),
        pht('Make Primary'));

      $button_remove = javelin_tag(
        'a',
        array(
          'class'   => 'button small grey',
          'href'    => $uri->alter('delete', $email->getID()),
          'sigil'   => 'workflow',
        ),
        pht('Remove'));

      $button_primary = phutil_tag(
        'a',
        array(
          'class' => 'button small disabled',
        ),
        pht('Primary'));

      if (!$email->getIsVerified()) {
        $action = $button_verify;
      } else if ($email->getIsPrimary()) {
        $action = $button_primary;
      } else {
        $action = $button_make_primary;
      }

      if ($email->getIsPrimary()) {
        $remove = $button_primary;
        $rowc[] = 'highlighted';
      } else {
        $remove = $button_remove;
        $rowc[] = null;
      }

      $rows[] = array(
        $email->getAddress(),
        $action,
        $remove,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Email'),
        pht('Status'),
        pht('Remove'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'action',
        'action',
      ));
    $table->setRowClasses($rowc);
    $table->setColumnVisibility(
      array(
        true,
        true,
        $editable,
      ));

    $view = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();
    $header->setHeader(pht('Email Addresses'));

    if ($editable) {
      $button = new PHUIButtonView();
      $button->setText(pht('Add New Address'));
      $button->setTag('a');
      $button->setHref($uri->alter('new', 'true'));
      $button->setIcon('fa-plus');
      $button->addSigil('workflow');
      $header->addActionLink($button);
    }
    $view->setHeader($header);
    $view->setTable($table);

    return $view;
  }

  private function returnNewAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $new) {

    $user = $this->getUser();
    $viewer = $this->getViewer();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());

    $e_email = true;
    $email   = null;
    $errors  = array();
    if ($request->isDialogFormPost()) {
      $email = trim($request->getStr('email'));

      if ($new == 'verify') {
        // The user clicked "Done" from the "an email has been sent" dialog.
        return id(new AphrontReloadResponse())->setURI($uri);
      }

      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhabricatorSettingsAddEmailAction(),
        1);

      if (!strlen($email)) {
        $e_email = pht('Required');
        $errors[] = pht('Email is required.');
      } else if (!PhabricatorUserEmail::isValidAddress($email)) {
        $e_email = pht('Invalid');
        $errors[] = PhabricatorUserEmail::describeValidAddresses();
      } else if (!PhabricatorUserEmail::isAllowedAddress($email)) {
        $e_email = pht('Disallowed');
        $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
      }
      if ($e_email === true) {
        $application_email = id(new PhabricatorMetaMTAApplicationEmailQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withAddresses(array($email))
          ->executeOne();
        if ($application_email) {
          $e_email = pht('In Use');
          $errors[] = $application_email->getInUseMessage();
        }
      }

      if (!$errors) {
        $object = id(new PhabricatorUserEmail())
          ->setAddress($email)
          ->setIsVerified(0);

        // If an administrator is editing a mailing list, automatically verify
        // the address.
        if ($viewer->getPHID() != $user->getPHID()) {
          if ($viewer->getIsAdmin()) {
            $object->setIsVerified(1);
          }
        }

        try {
          id(new PhabricatorUserEditor())
            ->setActor($viewer)
            ->addEmail($user, $object);

          if ($object->getIsVerified()) {
            // If we autoverified the address, just reload the page.
            return id(new AphrontReloadResponse())->setURI($uri);
          }

          $object->sendVerificationEmail($user);

          $dialog = $this->newDialog()
            ->addHiddenInput('new',  'verify')
            ->setTitle(pht('Verification Email Sent'))
            ->appendChild(phutil_tag('p', array(), pht(
              'A verification email has been sent. Click the link in the '.
              'email to verify your address.')))
            ->setSubmitURI($uri)
            ->addSubmitButton(pht('Done'));

          return id(new AphrontDialogResponse())->setDialog($dialog);
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $e_email = pht('Duplicate');
          $errors[] = pht('Another user already has this email.');
        }
      }
    }

    if ($errors) {
      $errors = id(new PHUIInfoView())
        ->setErrors($errors);
    }

    $form = id(new PHUIFormLayoutView())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($email)
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));

    $dialog = $this->newDialog()
      ->addHiddenInput('new', 'true')
      ->setTitle(pht('New Address'))
      ->appendChild($errors)
      ->appendChild($form)
      ->addSubmitButton(pht('Save'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function returnDeleteAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_id) {
    $user = $this->getUser();
    $viewer = $this->getViewer();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());

    // NOTE: You can only delete your own email addresses, and you can not
    // delete your primary address.
    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'id = %d AND userPHID = %s AND isPrimary = 0',
      $email_id,
      $user->getPHID());

    if (!$email) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->removeEmail($user, $email);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $address = $email->getAddress();

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('delete', $email_id)
      ->setTitle(pht("Really delete address '%s'?", $address))
      ->appendParagraph(
        pht(
          'Are you sure you want to delete this address? You will no '.
          'longer be able to use it to login.'))
      ->appendParagraph(
        pht(
          'Note: Removing an email address from your account will invalidate '.
          'any outstanding password reset links.'))
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function returnVerifyAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_id) {
    $user = $this->getUser();
    $viewer = $this->getViewer();

    // NOTE: You can only send more email for your unverified addresses.
    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'id = %d AND userPHID = %s AND isVerified = 0',
      $email_id,
      $user->getPHID());

    if (!$email) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $email->sendVerificationEmail($user);
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $address = $email->getAddress();

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('verify', $email_id)
      ->setTitle(pht('Send Another Verification Email?'))
      ->appendChild(phutil_tag('p', array(), pht(
        'Send another copy of the verification email to %s?',
        $address)))
      ->addSubmitButton(pht('Send Email'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function returnPrimaryAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_id) {
    $user = $this->getUser();
    $viewer = $this->getViewer();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());

    // NOTE: You can only make your own verified addresses primary.
    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'id = %d AND userPHID = %s AND isVerified = 1 AND isPrimary = 0',
      $email_id,
      $user->getPHID());

    if (!$email) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->changePrimaryEmail($user, $email);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $address = $email->getAddress();

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('primary', $email_id)
      ->setTitle(pht('Change primary email address?'))
      ->appendParagraph(
        pht(
          'If you change your primary address, Phabricator will send all '.
          'email to %s.',
          $address))
      ->appendParagraph(
        pht(
          'Note: Changing your primary email address will invalidate any '.
          'outstanding password reset links.'))
      ->addSubmitButton(pht('Change Primary Address'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
