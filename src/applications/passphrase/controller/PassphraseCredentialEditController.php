<?php

final class PassphraseCredentialEditController extends PassphraseController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $credential = id(new PassphraseCredentialQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$credential) {
        return new Aphront404Response();
      }

      $type = PassphraseCredentialType::getTypeByConstant(
        $credential->getCredentialType());
      if (!$type) {
        throw new Exception(pht('Credential has invalid type "%s"!', $type));
      }

      if (!$type->isCreateable()) {
        throw new Exception(
          pht('Credential has noncreateable type "%s"!', $type));
      }

      $is_new = false;
    } else {
      $type_const = $request->getStr('type');
      $type = PassphraseCredentialType::getTypeByConstant($type_const);
      if (!$type) {
        return new Aphront404Response();
      }

      $credential = PassphraseCredential::initializeNewCredential($viewer)
        ->setCredentialType($type->getCredentialType())
        ->setProvidesType($type->getProvidesType());

      $is_new = true;

      // Prefill username if provided.
      $credential->setUsername($request->getStr('username'));
    }

    $errors = array();

    $v_name = $credential->getName();
    $e_name = true;

    $v_desc = $credential->getDescription();

    $v_username = $credential->getUsername();
    $e_username = true;

    $bullet = "\xE2\x80\xA2";

    $v_secret = $credential->getSecretID() ? str_repeat($bullet, 32) : null;

    $validation_exception = null;
    $errors = array();
    $e_password = null;
    if ($request->isFormPost()) {

      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_username = $request->getStr('username');
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');

      $v_secret = $request->getStr('secret');
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
        $type_view_policy = PhabricatorTransactions::TYPE_VIEW_POLICY;
        $type_edit_policy = PhabricatorTransactions::TYPE_EDIT_POLICY;

        $xactions = array();

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_name)
          ->setNewValue($v_name);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_desc)
          ->setNewValue($v_desc);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_username)
          ->setNewValue($v_username);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_view_policy)
          ->setNewValue($v_view_policy);

        $xactions[] = id(new PassphraseCredentialTransaction())
          ->setTransactionType($type_edit_policy)
          ->setNewValue($v_edit_policy);

        // Open a transaction in case we're writing a new secret; this limits
        // the amount of code which handles secret plaintexts.
        $credential->openTransaction();

        $min_secret = str_replace($bullet, '', trim($v_decrypt));
        if (strlen($min_secret)) {
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

    if ($request->isAjax()) {
      $form = new PHUIFormLayoutView();
    } else {
      $form = id(new AphrontFormView())
        ->setUser($viewer);
    }

    $form
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
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($credential)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($credential)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormDividerControl()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('username')
          ->setLabel(pht('Login/Username'))
          ->setValue($v_username)
          ->setError($e_username))
      ->appendChild(
        $secret_control
          ->setName('secret')
          ->setLabel($type->getSecretLabel())
          ->setValue($v_secret));

    if ($type->shouldShowPasswordField()) {
      $form->appendChild(
        id(new AphrontFormPasswordControl())
          ->setName('password')
          ->setLabel($type->getPasswordLabel())
          ->setError($e_password));
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $title = pht('Create Credential');
      $header = pht('Create New Credential');
      $crumbs->addTextCrumb(pht('Create'));
    } else {
      $title = pht('Edit Credential');
      $header = pht('Edit Credential %s', 'K'.$credential->getID());
      $crumbs->addTextCrumb(
        'K'.$credential->getID(),
        '/K'.$credential->getID());
      $crumbs->addTextCrumb(pht('Edit'));
    }

    if ($request->isAjax()) {
      $errors = id(new AphrontErrorView())->setErrors($errors);

      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle($title)
        ->appendChild($errors)
        ->appendChild($form)
        ->addSubmitButton(pht('Create Credential'))
        ->addCancelButton($this->getApplicationURI());

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save'))
        ->addCancelButton($this->getApplicationURI()));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setFormErrors($errors)
      ->setValidationException($validation_exception)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
