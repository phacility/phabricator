<?php

final class PassphraseCredentialEditController extends PassphraseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $credential = id(new PassphraseCredentialQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$credential) {
        return new Aphront404Response();
      }

      $type = $this->getCredentialType($credential->getCredentialType());
      $type_const = $type->getCredentialType();

      $is_new = false;
    } else {
      $type_const = $request->getStr('type');
      $type = $this->getCredentialType($type_const);

      if (!$type->isCreateable()) {
        throw new Exception(
          pht(
            'Credential has noncreateable type "%s"!',
            $type_const));
      }

      $credential = PassphraseCredential::initializeNewCredential($viewer)
        ->setCredentialType($type->getCredentialType())
        ->setProvidesType($type->getProvidesType())
        ->attachImplementation($type);

      $is_new = true;

      // Prefill username if provided.
      $credential->setUsername((string)$request->getStr('username'));

      if (!$request->getStr('isInitialized')) {
        $type->didInitializeNewCredential($viewer, $credential);
      }
    }

    $errors = array();

    $v_name = $credential->getName();
    $e_name = true;

    $v_desc = $credential->getDescription();
    $v_space = $credential->getSpacePHID();

    $v_username = $credential->getUsername();
    $e_username = true;

    $v_is_locked = false;

    $bullet = "\xE2\x80\xA2";

    $v_secret = $credential->getSecretID() ? str_repeat($bullet, 32) : null;
    if ($is_new && ($v_secret === null)) {
      // If we're creating a new credential, the credential type may have
      // populated the secret for us (for example, generated an SSH key). In
      // this case,
      try {
        $v_secret = $credential->getSecret()->openEnvelope();
      } catch (Exception $ex) {
        // Ignore this.
      }
    }

    $validation_exception = null;
    $errors = array();
    $e_password = null;
    if ($request->isFormPost()) {

      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_username = $request->getStr('username');
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');
      $v_is_locked = $request->getStr('lock');

      $v_secret = $request->getStr('secret');
      $v_space = $request->getStr('spacePHID');
      $v_password = $request->getStr('password');
      $v_decrypt = $v_secret;

      $env_secret = new PhutilOpaqueEnvelope($v_secret);
      $env_password = new PhutilOpaqueEnvelope($v_password);

      if ($type->requiresPassword($env_secret)) {
        if (strlen($v_password)) {
          $v_decrypt = $type->decryptSecret($env_secret, $env_password);
          if ($v_decrypt === null) {
            $e_password = pht('Incorrect');
            $errors[] = pht(
              'This key requires a password, but the password you provided '.
              'is incorrect.');
          } else {
            $v_decrypt = $v_decrypt->openEnvelope();
          }
        } else {
          $e_password = pht('Required');
          $errors[] = pht(
            'This key requires a password. You must provide the password '.
            'for the key.');
        }
      }

      if (!$errors) {
        $type_name = PassphraseCredentialTransaction::TYPE_NAME;
        $type_desc = PassphraseCredentialTransaction::TYPE_DESCRIPTION;
        $type_username = PassphraseCredentialTransaction::TYPE_USERNAME;
        $type_destroy = PassphraseCredentialTransaction::TYPE_DESTROY;
        $type_secret_id = PassphraseCredentialTransaction::TYPE_SECRET_ID;
        $type_is_locked = PassphraseCredentialTransaction::TYPE_LOCK;
        $type_view_policy = PhabricatorTransactions::TYPE_VIEW_POLICY;
        $type_edit_policy = PhabricatorTransactions::TYPE_EDIT_POLICY;
        $type_space = PhabricatorTransactions::TYPE_SPACE;

        $xactions = array();

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_name)
          ->setNewValue($v_name);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_desc)
          ->setNewValue($v_desc);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_view_policy)
          ->setNewValue($v_view_policy);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_edit_policy)
          ->setNewValue($v_edit_policy);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_space)
          ->setNewValue($v_space);

        // Open a transaction in case we're writing a new secret; this limits
        // the amount of code which handles secret plaintexts.
        $credential->openTransaction();

        if (!$credential->getIsLocked()) {
          if ($type->shouldRequireUsername()) {
            $xactions[] = id(new PassphraseCredentialTransaction())
            ->setTransactionType($type_username)
            ->setNewValue($v_username);
          }
          // If some value other than a sequence of bullets was provided for
          // the credential, update it. In particular, note that we are
          // explicitly allowing empty secrets: one use case is HTTP auth where
          // the username is a secret token which covers both identity and
          // authentication.

          if (!preg_match('/^('.$bullet.')+$/', trim($v_decrypt))) {
            // If the credential was previously destroyed, restore it when it is
            // edited if a secret is provided.
            $xactions[] = id(new PassphraseCredentialTransaction())
              ->setTransactionType($type_destroy)
              ->setNewValue(0);

            $new_secret = id(new PassphraseSecret())
              ->setSecretData($v_decrypt)
              ->save();
            $xactions[] = id(new PassphraseCredentialTransaction())
              ->setTransactionType($type_secret_id)
              ->setNewValue($new_secret->getID());
          }

          $xactions[] = id(new PassphraseCredentialTransaction())
            ->setTransactionType($type_is_locked)
            ->setNewValue($v_is_locked);
        }

        try {
          $editor = id(new PassphraseCredentialTransactionEditor())
            ->setActor($viewer)
            ->setContinueOnNoEffect(true)
            ->setContentSourceFromRequest($request)
            ->applyTransactions($credential, $xactions);

          $credential->saveTransaction();

          if ($request->isAjax()) {
            return id(new AphrontAjaxResponse())->setContent(
              array(
                'phid' => $credential->getPHID(),
                'name' => 'K'.$credential->getID().' '.$credential->getName(),
              ));
          } else {
            return id(new AphrontRedirectResponse())
              ->setURI('/K'.$credential->getID());
          }
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $credential->killTransaction();

          $validation_exception = $ex;

          $e_name = $ex->getShortMessage($type_name);
          $e_username = $ex->getShortMessage($type_username);

          $credential->setViewPolicy($v_view_policy);
          $credential->setEditPolicy($v_edit_policy);
        }
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($credential)
      ->execute();

    $secret_control = $type->newSecretControl();
    $credential_is_locked = $credential->getIsLocked();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('isInitialized', true)
      ->addHiddenInput('type', $type_const)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Credential Type'))
          ->setValue($type->getCredentialTypeName()))
      ->appendChild(
        id(new AphrontFormDividerControl()))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($credential)
          ->setSpacePHID($v_space)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($credential)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormDividerControl()));

    if ($credential_is_locked) {
      $form->appendRemarkupInstructions(
        pht('This credential is permanently locked and can not be edited.'));
    }

    if ($type->shouldRequireUsername()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('username')
          ->setLabel(pht('Login/Username'))
          ->setValue($v_username)
          ->setDisabled($credential_is_locked)
          ->setError($e_username));
    }

    $form->appendChild(
      $secret_control
        ->setName('secret')
        ->setLabel($type->getSecretLabel())
        ->setDisabled($credential_is_locked)
        ->setValue($v_secret));

    if ($type->shouldShowPasswordField()) {
      $form->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setName('password')
          ->setLabel($type->getPasswordLabel())
          ->setDisabled($credential_is_locked)
          ->setError($e_password));
    }

    if ($is_new) {
      $form->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'lock',
            1,
            array(
              phutil_tag('strong', array(), pht('Lock Permanently:')),
              ' ',
              pht('Prevent the secret from being revealed or changed.'),
            ),
            $v_is_locked)
          ->setDisabled($credential_is_locked));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    if ($is_new) {
      $title = pht('Create New Credential');
      $crumbs->addTextCrumb(pht('Create'));
      $cancel_uri = $this->getApplicationURI();
      $header_icon = 'fa-plus-square';
    } else {
      $title = pht('Edit Credential: %s', $credential->getName());
      $crumbs->addTextCrumb(
        'K'.$credential->getID(),
        '/K'.$credential->getID());
      $crumbs->addTextCrumb(pht('Edit'));
      $cancel_uri = '/K'.$credential->getID();
      $header_icon = 'fa-pencil';
    }

    if ($request->isAjax()) {
      if ($errors) {
        $errors = id(new PHUIInfoView())->setErrors($errors);
      }

      return $this->newDialog()
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle($title)
        ->appendChild($errors)
        ->appendChild($form->buildLayoutView())
        ->addSubmitButton(pht('Create Credential'))
        ->addCancelButton($cancel_uri);
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save'))
        ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Credential'))
      ->setFormErrors($errors)
      ->setValidationException($validation_exception)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function getCredentialType($type_const) {
    $type = PassphraseCredentialType::getTypeByConstant($type_const);

    if (!$type) {
      throw new Exception(
        pht('Credential has invalid type "%s"!', $type_const));
    }

    return $type;
  }

}
