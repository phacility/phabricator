<?php

final class BMOExternalAccountSearchConduitAPIMethod
  extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'bmoexternalaccount.search';
  }

  public function getMethodDescription() {
    return pht('Retrieve external user PHID data based on BMO ID.');
  }

  public function defineParamTypes() {
    return array('accountids' => 'required list<string>');
  }

  protected function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $account_ids = $request->getValue('accountids');

    $config = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withProviderClasses(array('PhabricatorBMOAuthProvider'))
      ->executeOne();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($request->getUser())
      ->withProviderConfigPHIDs(array($config->getPHID()))
      ->withRawAccountIdentifiers($account_ids)
      ->needAccountIdentifiers(true)
      ->execute();

    $result = array();
    foreach ($accounts as $account) {
      $identifiers = $account->getAccountIdentifiers();
      $bmo_id = head($identifiers)->getIdentifierRaw();
      if (!$bmo_id) {
        continue;
      }
      $result[] = array(
        'id'   => $bmo_id,                 // The BMO ID
        'phid' => $account->getUserPHID()  // The Phabricator User PHID
      );
    }

    return $result;
  }
}
