<?php

final class PhabricatorAuthSSHKeyGenerateController
  extends PhabricatorAuthSSHKeyController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $key = $this->newKeyForObjectPHID($request->getStr('objectPHID'));
    if (!$key) {
      return new Aphront404Response();
    }

    $cancel_uri = $key->getObject()->getSSHPublicKeyManagementURI($viewer);

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $cancel_uri);

    if ($request->isFormPost()) {
      $default_name = $key->getObject()->getSSHKeyDefaultName();

      $keys = PhabricatorSSHKeyGenerator::generateKeypair();
      list($public_key, $private_key) = $keys;

      $file = PhabricatorFile::newFromFileData(
        $private_key,
        array(
          'name' => $default_name.'.key',
          'ttl.relative' => phutil_units('10 minutes in seconds'),
          'viewPolicy' => $viewer->getPHID(),
        ));

      $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($public_key);

      $type = $public_key->getType();
      $body = $public_key->getBody();
      $comment = pht('Generated');

      $entire_key = "{$type} {$body} {$comment}";

      $type_create = PhabricatorTransactions::TYPE_CREATE;
      $type_name = PhabricatorAuthSSHKeyTransaction::TYPE_NAME;
      $type_key = PhabricatorAuthSSHKeyTransaction::TYPE_KEY;

      $xactions = array();

      $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_CREATE);

      $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($default_name);

      $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
        ->setTransactionType($type_key)
        ->setNewValue($entire_key);

      $editor = id(new PhabricatorAuthSSHKeyEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($key, $xactions);

      // NOTE: We're disabling workflow on submit so the download works. We're
      // disabling workflow on cancel so the page reloads, showing the new
      // key.

      return $this->newDialog()
        ->setTitle(pht('Download Private Key'))
        ->setDisableWorkflowOnCancel(true)
        ->setDisableWorkflowOnSubmit(true)
        ->setSubmitURI($file->getDownloadURI())
        ->appendParagraph(
          pht(
            'A keypair has been generated, and the public key has been '.
            'added as a recognized key. Use the button below to download '.
            'the private key.'))
        ->appendParagraph(
          pht(
            'After you download the private key, it will be destroyed. '.
            'You will not be able to retrieve it if you lose your copy.'))
        ->addSubmitButton(pht('Download Private Key'))
        ->addCancelButton($cancel_uri, pht('Done'));
    }

    try {
      PhabricatorSSHKeyGenerator::assertCanGenerateKeypair();

      return $this->newDialog()
        ->setTitle(pht('Generate New Keypair'))
        ->addHiddenInput('objectPHID', $key->getObject()->getPHID())
        ->appendParagraph(
          pht(
            'This workflow will generate a new SSH keypair, add the public '.
            'key, and let you download the private key.'))
        ->appendParagraph(
          pht('Phabricator will not retain a copy of the private key.'))
        ->addSubmitButton(pht('Generate New Keypair'))
        ->addCancelButton($cancel_uri);
    } catch (Exception $ex) {
      return $this->newDialog()
        ->setTitle(pht('Unable to Generate Keys'))
        ->appendParagraph($ex->getMessage())
        ->addCancelButton($cancel_uri);
    }
  }

}
