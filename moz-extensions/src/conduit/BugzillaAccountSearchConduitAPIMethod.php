<?php

final class BugzillaAccountSearchConduitAPIMethod
  extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'bugzilla.account.search';
  }

  public function getMethodDescription() {
    return pht('Retrieve Bugzilla data based on Bugzilla ID or Phabricator PHID.');
  }

  public function defineParamTypes() {
    return array('ids' => 'optional list<string>',
                 'phids' => 'optional list<string>');
  }

  protected function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $bugzilla_ids = $request->getValue('ids');
    $phab_phids   = $request->getValue('phids');

    if (!$bugzilla_ids && !$phab_phids) {
      return array();
    }

    $config = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withProviderClasses(array('PhabricatorBMOAuthProvider'))
      ->executeOne();

    $query = id(new PhabricatorExternalAccountQuery())
    ->setViewer($request->getUser())
    ->withProviderConfigPHIDs(array($config->getPHID()))
    ->needAccountIdentifiers(true);

    if ($bugzilla_ids) {
      $query->withRawAccountIdentifiers($bugzilla_ids);
    }
    elseif ($phab_phids) {
      $query->withUserPHIDs($phab_phids);
    }

    $accounts = $query->execute();

    $results = array();
    foreach ($accounts as $account) {
      $identifiers = $account->getAccountIdentifiers();
      $bmo_id = head($identifiers)->getIdentifierRaw();
      if (!$bmo_id) {
        continue;
      }
      $results[] = array(
        'id'   => $bmo_id,                  // The Bugzilla ID
        'phid' => $account->getUserPHID()   // The Phabricator User PHID
      );
    }

    return $results;
  }
}
