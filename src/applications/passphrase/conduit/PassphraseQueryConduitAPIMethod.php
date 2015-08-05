<?php

final class PassphraseQueryConduitAPIMethod
  extends PassphraseConduitAPIMethod {

  public function getAPIMethodName() {
    return 'passphrase.query';
  }

  public function getMethodDescription() {
    return pht('Query credentials.');
  }

  public function newQueryObject() {
    return new PassphraseCredentialQuery();
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<int>',
      'phids' => 'optional list<phid>',
      'needSecrets' => 'optional bool',
      'needPublicKeys' => 'optional bool',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = $this->newQueryForRequest($request);

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

      $material = array();

      $secret = null;
      if ($request->getValue('needSecrets')) {
        if ($credential->getAllowConduit()) {
          $secret = $credential->getSecret();
          if ($secret) {
            $secret = $secret->openEnvelope();
          } else {
            $material['destroyed'] = pht(
              'The private material for this credential has been '.
              'destroyed.');
          }
        }
      }

      switch ($credential->getCredentialType()) {
        case PassphraseSSHPrivateKeyFileCredentialType::CREDENTIAL_TYPE:
          if ($secret) {
            $material['file'] = $secret;
          }
          if ($public_key) {
            $material['publicKey'] = $public_key;
          }
          break;
        case PassphraseSSHGeneratedKeyCredentialType::CREDENTIAL_TYPE:
        case PassphraseSSHPrivateKeyTextCredentialType::CREDENTIAL_TYPE:
          if ($secret) {
            $material['privateKey'] = $secret;
          }
          if ($public_key) {
            $material['publicKey'] = $public_key;
          }
          break;
        case PassphrasePasswordCredentialType::CREDENTIAL_TYPE:
          if ($secret) {
            $material['password'] = $secret;
          }
          break;
      }

      if (!$credential->getAllowConduit()) {
        $material['noAPIAccess'] = pht(
          'This private material for this credential is not accessible via '.
          'API calls.');
      }

      $results[$credential->getPHID()] = array(
        'id' => $credential->getID(),
        'phid' => $credential->getPHID(),
        'type' => $credential->getCredentialType(),
        'name' => $credential->getName(),
        'description' => $credential->getDescription(),
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
