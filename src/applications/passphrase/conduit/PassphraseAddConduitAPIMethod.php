<?php

final class PassphraseAddConduitAPIMethod
  extends PassphraseConduitAPIMethod {

  public function getAPIMethodName() {
    return 'passphrase.add';
  }

  public function getMethodDescription() {
    return pht('Add credentials.');
  }

  public function defineParamTypes() {
    return array(
      'type' => 'string',
      'username' => 'string',
      'name' => 'string',
      'description' => 'string',
      'secret' => 'optional string',
      'password' => 'optional string',
      'viewPolicy' => 'optional string',
      'editPolicy' => 'optional string',
      'lock' => 'string',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
      // see PassphraseCredentialType.php
      'ERR-INVALID-CREDTYPE' => 'Invalid credential type',
      'ERR-CRED-NOT-CREATABLE' => 'Credential not creatable',
      'ERR-REQUIRES-PASSWORD' => 'Credential type requires password',
      'ERR-INCORRECT-PASSWORD' => 'Incorrect password',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $actor = $request->getUser();

    $type_const = $request->getValue('type');
    $type = PassphraseCredentialType::getTypeByConstant($type_const);
    if (!$type) {
      throw new ConduitException('ERR-INVALID-CREDTYPE');
    }
    if ($type_const != 'password') {
      throw new ConduitException('Credential type not supported yet');
    }
    if (!$type->isCreateable()) {
      throw new ConduitException('ERR-CRED-NOT-CREATABLE');
    }

    $credential = PassphraseCredential::initializeNewCredential($actor)
      ->setCredentialType($type->getCredentialType())
      ->setProvidesType($type->getProvidesType());

    $credential->setUsername($request->getValue('username'));

    $v_name = $request->getValue('name');
    $v_desc = $request->getValue('description');
    $v_username = $request->getValue('username');
    $v_view_policy = $request->getValue('viewPolicy');
    $v_edit_policy = $request->getValue('editPolicy');
    $v_is_locked = $request->getValue('lock');

    $v_secret = $request->getValue('secret');
    $v_password = $request->getValue('password');
    $v_decrypt = $v_secret;

    $env_secret = new PhutilOpaqueEnvelope($v_secret);
    $env_password = new PhutilOpaqueEnvelope($v_password);

    if ($v_view_policy === null) {
       $v_view_policy = $actor->getPHID();
    }

   if ($v_edit_policy === null) {
       $v_edit_policy = $actor->getPHID();
    }

    if ($type->requiresPassword($env_secret)) {
      if (strlen($v_password)) {
        $v_decrypt = $type->decryptSecret($env_secret, $env_password);
        if ($v_decrypt === null) {
          throw new ConduitException('ERR-INCORRECT-PASSWORD');
          } else {
            $v_decrypt = $v_decrypt->openEnvelope();
          }
      } else {
          throw new ConduitException('ERR-REQUIRES-PASSWORD');
        }
    }

    $type_name = PassphraseCredentialTransaction::TYPE_NAME;
    $type_desc = PassphraseCredentialTransaction::TYPE_DESCRIPTION;
    $type_username = PassphraseCredentialTransaction::TYPE_USERNAME;
    $type_destroy = PassphraseCredentialTransaction::TYPE_DESTROY;
    $type_secret_id = PassphraseCredentialTransaction::TYPE_SECRET_ID;
    $type_is_locked = PassphraseCredentialTransaction::TYPE_LOCK;
    $type_view_policy = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $type_edit_policy = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $type_conduit_policy = PassphraseCredentialTransaction::TYPE_CONDUIT;

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
      ->setTransactionType($type_conduit_policy)
      ->setNewValue(true);

    $credential->openTransaction();

    if (!$credential->getIsLocked()) {
      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType($type_username)
        ->setNewValue($v_username);

      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType($type_destroy)
        ->setNewValue(0);

      $new_secret = id(new PassphraseSecret())
        ->setSecretData($v_decrypt)
        ->save();
      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType($type_secret_id)
        ->setNewValue($new_secret->getID());

      $xactions[] = id(new PassphraseCredentialTransaction())
        ->setTransactionType($type_is_locked)
        ->setNewValue($v_is_locked);
    }

    try {
      $editor = id(new PassphraseCredentialTransactionEditor())
        ->setActor($actor)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromConduitRequest($request)
        ->applyTransactions($credential, $xactions);

      $credential->saveTransaction();
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      $credential->killTransaction();
      $credential->setViewPolicy($v_view_policy);
      $credential->setEditPolicy($v_edit_policy);
      throw new ConduitException($ex->getShortMessage($type_name));
    }

    return array(
      'id' => $credential->getID(),
      'phid' => $credential->getPHID(),
      'type' => $credential->getCredentialType(),
      'name' => $credential->getName(),
      'uri' =>
        PhabricatorEnv::getProductionURI('/'.$credential->getMonogram()),
      'monogram' => $credential->getMonogram(),
      'username' => $credential->getUsername(),
    );
  }
}
