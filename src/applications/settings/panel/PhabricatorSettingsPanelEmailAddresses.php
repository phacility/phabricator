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

      $button_verify = javelin_render_tag(
        'a',
        array(
          'class' => 'button small grey',
          'href'  => $uri->alter('verify', $email->getID()),
          'sigil' => 'workflow',
        ),
        'Verify');

      $button_make_primary = javelin_render_tag(
        'a',
        array(
          'class' => 'button small grey',
          'href'  => $uri->alter('primary', $email->getID()),
          'sigil' => 'workflow',
        ),
        'Make Primary');

      $button_remove = javelin_render_tag(
        'a',
        array(
          'class'   => 'button small grey',
          'href'    => $uri->alter('delete', $email->getID()),
          'sigil'   => 'workflow'
        ),
        'Remove');

      $button_primary = phutil_render_tag(
        'a',
        array(
          'class' => 'button small disabled',
        ),
        'Primary');

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
        phutil_escape_html($email->getAddress()),
        $action,
        $remove,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Email',
        'Status',
        'Remove',
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
        javelin_render_tag(
          'a',
          array(
            'href'      => $uri->alter('new', 'true'),
            'class'     => 'green button',
            'sigil'     => 'workflow',
          ),
          'Add New Address'));
    }
    $view->setHeader('Email Addresses');
    $view->appendChild($table);

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
        $e_email = 'Required';
        $errors[] = 'Email is required.';
      } else if (!PhabricatorUserEmail::isAllowedAddress($email)) {
        $e_email = 'Invalid';
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
            ->setTitle('Verification Email Sent')
            ->appendChild(
              '<p>A verification email has been sent. Click the link in the '.
              'email to verify your address.</p>')
            ->setSubmitURI($uri)
            ->addSubmitButton('Done');

          return id(new AphrontDialogResponse())->setDialog($dialog);
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $email = 'Duplicate';
          $errors[] = 'Another user already has this email.';
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
          ->setLabel('Email')
          ->setName('email')
          ->setValue($request->getStr('email'))
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->addHiddenInput('new', 'true')
      ->setTitle('New Address')
      ->appendChild($errors)
      ->appendChild($form)
      ->addSubmitButton('Save')
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
      ->setTitle("Really delete address '{$address}'?")
      ->appendChild(
        '<p>Are you sure you want to delete this address? You will no '.
        'longer be able to use it to login.</p>')
      ->addSubmitButton('Delete')
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
      ->setTitle("Send Another Verification Email?")
      ->appendChild(
        '<p>Send another copy of the verification email to '.
        phutil_escape_html($address).'?</p>')
      ->addSubmitButton('Send Email')
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
      ->setTitle("Change primary email address?")
      ->appendChild(
        '<p>If you change your primary address, Phabricator will send all '.
        'email to '.phutil_escape_html($address).'.</p>')
      ->addSubmitButton('Change Primary Address')
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
