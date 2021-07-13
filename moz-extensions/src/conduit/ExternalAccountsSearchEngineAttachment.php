<?php

final class ExternalAccountsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('External Accounts');
  }

  public function getAttachmentDescription() {
    return pht('Get external account data about users.');
  }

  public function willLoadAttachmentData($query, $spec) {
    return true;
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($this->getViewer())
      ->withUserPHIDs(array($object->getPHID()))
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_VIEW))
      ->needAccountIdentifiers(true)
      ->execute();

    $results = array();
    foreach ($accounts as $account) {
      $identifiers = $account->getAccountIdentifiers();
      if (!$identifiers) {
        continue;
      }
      $account_id = head($identifiers)->getIdentifierRaw();
      if (!$account_id) {
        continue;
      }

      $config = $account->getProviderConfig();
      $provider = $config->getProvider();

      $results[] = array(
        'id'   => $account_id,
        'type' => strtolower($provider->getProviderName())
      );
    }

    return array(
      'external-accounts' => $results,
    );
  }
}
