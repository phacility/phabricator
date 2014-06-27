<?php

final class PhabricatorSettingsPanelSSHKeys
  extends PhabricatorSettingsPanel {

  public function isEditableByAdministrators() {
    return true;
  }

  public function getPanelKey() {
    return 'ssh';
  }

  public function getPanelName() {
    return pht('SSH Public Keys');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $user = $this->getUser();

    $generate = $request->getStr('generate');
    if ($generate) {
      return $this->processGenerate($request);
    }

    $edit = $request->getStr('edit');
    $delete = $request->getStr('delete');
    if (!$edit && !$delete) {
      return $this->renderKeyListView($request);
    }

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());

    $id = nonempty($edit, $delete);

    if ($id && is_numeric($id)) {
      // NOTE: This prevents editing/deleting of keys not owned by the user.
      $key = id(new PhabricatorUserSSHKey())->loadOneWhere(
        'userPHID = %s AND id = %d',
        $user->getPHID(),
        (int)$id);
      if (!$key) {
        return new Aphront404Response();
      }
    } else {
      $key = new PhabricatorUserSSHKey();
      $key->setUserPHID($user->getPHID());
    }

    if ($delete) {
      return $this->processDelete($request, $key);
    }

    $e_name = true;
    $e_key = true;
    $errors = array();
    $entire_key = $key->getEntireKey();
    if ($request->isFormPost()) {
      $key->setName($request->getStr('name'));
      $entire_key = $request->getStr('key');

      if (!strlen($entire_key)) {
        $errors[] = pht('You must provide an SSH Public Key.');
        $e_key = pht('Required');
      } else {

        try {
          list($type, $body, $comment) = self::parsePublicKey($entire_key);

          $key->setKeyType($type);
          $key->setKeyBody($body);
          $key->setKeyHash(md5($body));
          $key->setKeyComment($comment);

          $e_key = null;
        } catch (Exception $ex) {
          $e_key = pht('Invalid');
          $errors[] = $ex->getMessage();
        }
      }

      if (!strlen($key->getName())) {
        $errors[] = pht('You must name this public key.');
        $e_name = pht('Required');
      } else {
        $e_name = null;
      }

      if (!$errors) {
        try {
          $key->save();
          return id(new AphrontRedirectResponse())
            ->setURI($this->getPanelURI());
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_key = pht('Duplicate');
          $errors[] = pht('This public key is already associated with a user '.
                      'account.');
        }
      }
    }

    $is_new = !$key->getID();

    if ($is_new) {
      $header = pht('Add New SSH Public Key');
      $save = pht('Add Key');
    } else {
      $header = pht('Edit SSH Public Key');
      $save   = pht('Save Changes');
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('edit', $is_new ? 'true' : $key->getID())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($key->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Public Key'))
          ->setName('key')
          ->setValue($entire_key)
          ->setError($e_key))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getPanelURI())
          ->setValue($save));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setFormErrors($errors)
      ->setForm($form);

    return $form_box;
  }

  private function renderKeyListView(AphrontRequest $request) {
    $user = $this->getUser();
    $viewer = $request->getUser();

    $keys = id(new PhabricatorUserSSHKey())->loadAllWhere(
      'userPHID = %s',
      $user->getPHID());

    $rows = array();
    foreach ($keys as $key) {
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $this->getPanelURI('?edit='.$key->getID()),
          ),
          $key->getName()),
        $key->getKeyComment(),
        $key->getKeyType(),
        phabricator_date($key->getDateCreated(), $viewer),
        phabricator_time($key->getDateCreated(), $viewer),
        javelin_tag(
          'a',
          array(
            'href' => $this->getPanelURI('?delete='.$key->getID()),
            'class' => 'small grey button',
            'sigil' => 'workflow',
          ),
          pht('Delete')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(pht("You haven't added any SSH Public Keys."));
    $table->setHeaders(
      array(
        pht('Name'),
        pht('Comment'),
        pht('Type'),
        pht('Created'),
        pht('Time'),
        '',
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        '',
        '',
        '',
        'right',
        'action',
      ));

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();

    $upload_icon = id(new PHUIIconView())
      ->setIconFont('fa-upload');
    $upload_button = id(new PHUIButtonView())
      ->setText(pht('Upload Public Key'))
      ->setHref($this->getPanelURI('?edit=true'))
      ->setTag('a')
      ->setIcon($upload_icon);

    try {
      PhabricatorSSHKeyGenerator::assertCanGenerateKeypair();
      $can_generate = true;
    } catch (Exception $ex) {
      $can_generate = false;
    }

    $generate_icon = id(new PHUIIconView())
      ->setIconFont('fa-lock');
    $generate_button = id(new PHUIButtonView())
      ->setText(pht('Generate Keypair'))
      ->setHref($this->getPanelURI('?generate=true'))
      ->setTag('a')
      ->setWorkflow(true)
      ->setDisabled(!$can_generate)
      ->setIcon($generate_icon);

    $header->setHeader(pht('SSH Public Keys'));
    $header->addActionLink($generate_button);
    $header->addActionLink($upload_button);

    $panel->setHeader($header);
    $panel->appendChild($table);

    return $panel;
  }

  private function processDelete(
    AphrontRequest $request,
    PhabricatorUserSSHKey $key) {

    $viewer = $request->getUser();
    $user = $this->getUser();

    $name = phutil_tag('strong', array(), $key->getName());

    if ($request->isDialogFormPost()) {
      $key->delete();
      return id(new AphrontReloadResponse())
        ->setURI($this->getPanelURI());
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('delete', $key->getID())
      ->setTitle(pht('Really delete SSH Public Key?'))
      ->appendChild(phutil_tag('p', array(), pht(
        'The key "%s" will be permanently deleted, and you will not longer be '.
          'able to use the corresponding private key to authenticate.',
        $name)))
      ->addSubmitButton(pht('Delete Public Key'))
      ->addCancelButton($this->getPanelURI());

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

  private function processGenerate(AphrontRequest $request) {
    $user = $this->getUser();
    $viewer = $request->getUser();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());


    $is_self = ($user->getPHID() == $viewer->getPHID());

    if ($request->isFormPost()) {
      $keys = PhabricatorSSHKeyGenerator::generateKeypair();
      list($public_key, $private_key) = $keys;

      $file = PhabricatorFile::buildFromFileDataOrHash(
        $private_key,
        array(
          'name' => 'id_rsa_phabricator.key',
          'ttl' => time() + (60 * 10),
          'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
        ));

      list($type, $body, $comment) = self::parsePublicKey($public_key);

      $key = id(new PhabricatorUserSSHKey())
        ->setUserPHID($user->getPHID())
        ->setName('id_rsa_phabricator')
        ->setKeyType($type)
        ->setKeyBody($body)
        ->setKeyHash(md5($body))
        ->setKeyComment(pht('Generated'))
        ->save();

      // NOTE: We're disabling workflow on submit so the download works. We're
      // disabling workflow on cancel so the page reloads, showing the new
      // key.

      if ($is_self) {
        $what_happened = pht(
          'The public key has been associated with your Phabricator '.
          'account. Use the button below to download the private key.');
      } else {
        $what_happened = pht(
          'The public key has been associated with the %s account. '.
          'Use the button below to download the private key.',
          phutil_tag('strong', array(), $user->getUsername()));
      }

      $dialog = id(new AphrontDialogView())
        ->setTitle(pht('Download Private Key'))
        ->setUser($viewer)
        ->setDisableWorkflowOnCancel(true)
        ->setDisableWorkflowOnSubmit(true)
        ->setSubmitURI($file->getDownloadURI())
        ->appendParagraph(
          pht(
            'Successfully generated a new keypair.'))
        ->appendParagraph($what_happened)
        ->appendParagraph(
          pht(
            'After you download the private key, it will be destroyed. '.
            'You will not be able to retrieve it if you lose your copy.'))
        ->addSubmitButton(pht('Download Private Key'))
        ->addCancelButton($this->getPanelURI(), pht('Done'));

      return id(new AphrontDialogResponse())
        ->setDialog($dialog);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addCancelButton($this->getPanelURI());

    try {
      PhabricatorSSHKeyGenerator::assertCanGenerateKeypair();

      if ($is_self) {
        $explain = pht(
          'This will generate an SSH keypair, associate the public key '.
          'with your account, and let you download the private key.');
      } else {
        $explain = pht(
          'This will generate an SSH keypair, associate the public key with '.
          'the %s account, and let you download the private key.',
          phutil_tag('strong', array(), $user->getUsername()));
      }

      $dialog
        ->addHiddenInput('generate', true)
        ->setTitle(pht('Generate New Keypair'))
        ->appendParagraph($explain)
        ->appendParagraph(
          pht(
            'Phabricator will not retain a copy of the private key.'))
        ->addSubmitButton(pht('Generate Keypair'));
    } catch (Exception $ex) {
      $dialog
        ->setTitle(pht('Unable to Generate Keys'))
        ->appendParagraph($ex->getMessage());
    }

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

  private static function parsePublicKey($entire_key) {
    $parts = str_replace("\n", '', trim($entire_key));

    // The third field (the comment) can have spaces in it, so split this
    // into a maximum of three parts.
    $parts = preg_split('/\s+/', $parts, 3);

    if (preg_match('/private\s*key/i', $entire_key)) {
      // Try to give the user a better error message if it looks like
      // they uploaded a private key.
      throw new Exception(
        pht('Provide your public key, not your private key!'));
    }

    switch (count($parts)) {
      case 1:
        throw new Exception(
          pht('Provided public key is not properly formatted.'));
      case 2:
        // Add an empty comment part.
        $parts[] = '';
        break;
      case 3:
        // This is the expected case.
        break;
    }

    list($type, $body, $comment) = $parts;

    $recognized_keys = array(
      'ssh-dsa',
      'ssh-dss',
      'ssh-rsa',
      'ecdsa-sha2-nistp256',
      'ecdsa-sha2-nistp384',
      'ecdsa-sha2-nistp521',
    );

    if (!in_array($type, $recognized_keys)) {
      $type_list = implode(', ', $recognized_keys);
      throw new Exception(
        pht(
          'Public key type should be one of: %s',
          $type_list));
    }

    return array($type, $body, $comment);
  }

}
