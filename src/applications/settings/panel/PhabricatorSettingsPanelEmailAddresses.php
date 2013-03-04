<?php

final class PhabricatorSettingsPanelEmailAddresses
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'email';
  }

  public function getPanelName() {
    return pht('Email Addresses');
  }

  public function getPanelGroup() {
    return pht('Email');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
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
          'sigil'   => 'workflow'
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

    $view = new AphrontPanelView();
    if ($editable) {
      $view->addButton(
        javelin_tag(
          'a',
          array(
            'href'      => $uri->alter('new', 'true'),
            'class'     => 'green button',
            'sigil'     => 'workflow',
          ),
          pht('Add New Address')));
    }
    $view->setHeader(pht('Email Addresses'));
    $view->appendChild($table);
    $view->setNoBackground();

    return $view;
  }

  private function returnNewAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $new) {

    $user = $request->getUser();

    $e_email = true;
    $email   = trim($request->getStr('email'));
    $errors  = array();
    if ($request->isDialogFormPost()) {

      if ($new == 'verify') {
        // The user clicked "Done" from the "an email has been sent" dialog.
        return id(new AphrontReloadResponse())->setURI($uri);
      }

      if (!strlen($email)) {
        $e_email = pht('Required');
        $errors[] = pht('Email is required.');
      } else if (!PhabricatorUserEmail::isAllowedAddress($email)) {
        $e_email = pht('Invalid');
        $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
      }

      if (!$errors) {
        $object = id(new PhabricatorUserEmail())
          ->setAddress($email)
          ->setIsVerified(0);

        try {

          id(new PhabricatorUserEditor())
            ->setActor($user)
            ->addEmail($user, $object);

          $object->sendVerificationEmail($user);

          $dialog = id(new AphrontDialogView())
            ->setUser($user)
            ->addHiddenInput('new',  'verify')
            ->setTitle(pht('Verification Email Sent'))
            ->appendChild(phutil_tag('p', array(), pht(
              'A verification email has been sent. Click the link in the '.
              'email to verify your address.')))
            ->setSubmitURI($uri)
            ->addSubmitButton(pht('Done'));

          return id(new AphrontDialogResponse())->setDialog($dialog);
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $email = pht('Duplicate');
          $errors[] = pht('Another user already has this email.');
        }
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    $form = id(new AphrontFormLayoutView())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($request->getStr('email'))
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
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

    $user = $request->getUser();

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
        ->setActor($user)
        ->removeEmail($user, $email);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $address = $email->getAddress();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->addHiddenInput('delete', $email_id)
      ->setTitle(pht("Really delete address '%s'?", $address))
      ->appendChild(phutil_tag('p', array(), pht(
        'Are you sure you want to delete this address? You will no '.
        'longer be able to use it to login.')))
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function returnVerifyAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_id) {

    $user = $request->getUser();

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
      ->setUser($user)
      ->addHiddenInput('verify', $email_id)
      ->setTitle(pht("Send Another Verification Email?"))
      ->appendChild(hsprintf(
        '<p>%s</p>',
        pht('Send another copy of the verification email to %s?', $address)))
      ->addSubmitButton(pht('Send Email'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function returnPrimaryAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_id) {

    $user = $request->getUser();

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
        ->setActor($user)
        ->changePrimaryEmail($user, $email);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $address = $email->getAddress();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->addHiddenInput('primary', $email_id)
      ->setTitle(pht("Change primary email address?"))
      ->appendChild(hsprintf(
        '<p>If you change your primary address, Phabricator will send'.
          ' all email to %s.</p>',
        $address))
      ->addSubmitButton(pht('Change Primary Address'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
