<?php

final class PhabricatorAuthSSHKeyEditController
  extends PhabricatorAuthSSHKeyController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $key = id(new PhabricatorAuthSSHKeyQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$key) {
        return new Aphront404Response();
      }

      $is_new = false;
    } else {
      $key = $this->newKeyForObjectPHID($request->getStr('objectPHID'));
      if (!$key) {
        return new Aphront404Response();
      }
      $is_new = true;
    }

    $cancel_uri = $key->getObject()->getSSHPublicKeyManagementURI($viewer);

    if ($key->getIsTrusted()) {
      $id = $key->getID();

      return $this->newDialog()
        ->setTitle(pht('Can Not Edit Trusted Key'))
        ->appendParagraph(
          pht(
            'This key is trusted. Trusted keys can not be edited. '.
            'Use %s to revoke trust before editing the key.',
            phutil_tag(
              'tt',
              array(),
              "bin/almanac untrust-key --id {$id}")))
        ->addCancelButton($cancel_uri, pht('Okay'));
    }

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $cancel_uri);

    $v_name = $key->getName();
    $e_name = strlen($v_name) ? null : true;

    $v_key = $key->getEntireKey();
    $e_key = strlen($v_key) ? null : true;

    $errors = array();
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_key = $request->getStr('key');

      if (!strlen($v_name)) {
        $errors[] = pht('You must provide a name for this public key.');
        $e_name = pht('Required');
      } else {
        $key->setName($v_name);
      }

      if (!strlen($v_key)) {
        $errors[] = pht('You must provide a public key.');
        $e_key = pht('Required');
      } else {
        try {
          $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($v_key);

          $type = $public_key->getType();
          $body = $public_key->getBody();
          $comment = $public_key->getComment();

          $key->setKeyType($type);
          $key->setKeyBody($body);
          $key->setKeyComment($comment);

          $e_key = null;
        } catch (Exception $ex) {
          $e_key = pht('Invalid');
          $errors[] = $ex->getMessage();
        }
      }

      if (!$errors) {
        try {
          $key->save();
          return id(new AphrontRedirectResponse())->setURI($cancel_uri);
        } catch (Exception $ex) {
          $e_key = pht('Duplicate');
          $errors[] = pht(
            'This public key is already associated with another user or '.
            'device. Each key must unambiguously identify a single unique '.
            'owner.');
        }
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setError($e_name)
          ->setValue($v_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Public Key'))
          ->setName('key')
          ->setValue($v_key)
          ->setError($e_key));

    if ($is_new) {
      $title = pht('Upload SSH Public Key');
      $save_button = pht('Upload Public Key');
      $form->addHiddenInput('objectPHID', $key->getObject()->getPHID());
    } else {
      $title = pht('Edit SSH Public Key');
      $save_button = pht('Save Changes');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($errors)
      ->appendForm($form)
      ->addSubmitButton($save_button)
      ->addCancelButton($cancel_uri);
  }

}
