<?php

final class PassphraseQueryConduitAPIMethod
  extends PassphraseConduitAPIMethod {

  public function getAPIMethodName() {
    return 'passphrase.query';
  }

  public function getMethodDescription() {
    return pht('Query credentials.');
  }

  public function defineParamTypes() {
    return array(
      'ids'           => 'optional list<int>',
      'phids'         => 'optional list<phid>',
      'needSecrets'   => 'optional bool',
      'needPublicKeys'   => 'optional bool',
    ) + $this->getPagerParamTypes();
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PassphraseCredentialQuery())
      ->setViewer($request->getUser());

    if ($request->getValue('ids')) {
      $query->withIDs($request->getValue('ids'));
    }

    if ($request->getValue('phids')) {
      $query->withPHIDs($request->getValue('phids'));
    }

    if ($request->getValue('needSecrets')) {
      $query->needSecrets(true);
    }

    $pager = $this->newPager($request);
    $credentials = $query->executeWithCursorPager($pager);

    $results = array();
    foreach ($credentials as $credential) {
      $type = PassphraseCredentialType::getTypeByConstant(
        $credential->getCredentialType());
      if (!$type) {
        continue;
      }

      $public_key = null;
      if ($request->getValue('needPublicKeys') && $type->hasPublicKey()) {
        $public_key = $type->getPublicKey(
          $request->getUser(),
          $credential);
      }

      $secret = null;
      if ($request->getValue('needSecrets')) {
        if ($credential->getAllowConduit()) {
          $secret = $credential->getSecret()->openEnvelope();
        }
      }

      $material = array();
      switch ($credential->getCredentialType()) {
        case PassphraseCredentialTypeSSHPrivateKeyFile::CREDENTIAL_TYPE:
          if ($secret) {
            $material['file'] = $secret;
          }
          if ($public_key) {
            $material['publicKey'] = $public_key;
          }
          break;
        case PassphraseCredentialTypeSSHPrivateKeyText::CREDENTIAL_TYPE:
          if ($secret) {
            $material['privateKey'] = $secret;
          }
          if ($public_key) {
            $material['publicKey'] = $public_key;
          }
          break;
        case PassphraseCredentialTypePassword::CREDENTIAL_TYPE:
          if ($secret) {
            $material['password'] = $secret;
          }
          break;
      }

      if (!$credential->getAllowConduit()) {
        $material['noAPIAccess'] = pht(
          'This credential\'s private material '.
          'is not accessible via API calls.');
      }

      $results[$credential->getPHID()] = array(
        'id' => $credential->getID(),
        'phid' => $credential->getPHID(),
        'type' => $credential->getCredentialType(),
        'name' => $credential->getName(),
        'uri' =>
          PhabricatorEnv::getProductionURI('/'.$credential->getMonogram()),
        'monogram' => $credential->getMonogram(),
        'username' => $credential->getUsername(),
        'material' => $material,
      );
    }

    $result = array(
      'data' => $results,
    );

    return $this->addPagerResults($result, $pager);
  }

}
