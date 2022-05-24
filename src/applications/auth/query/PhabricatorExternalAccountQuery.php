<?php

/**
 * NOTE: When loading ExternalAccounts for use in an authentication context
 * (that is, you're going to act as the account or link identities or anything
 * like that) you should require CAN_EDIT capability even if you aren't actually
 * editing the ExternalAccount.
 *
 * ExternalAccounts have a permissive CAN_VIEW policy (like users) because they
 * interact directly with objects and can leave comments, sign documents, etc.
 * However, CAN_EDIT is restricted to users who own the accounts.
 */
final class PhabricatorExternalAccountQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $userPHIDs;
  private $needImages;
  private $accountSecrets;
  private $providerConfigPHIDs;
  private $needAccountIdentifiers;
  private $rawAccountIdentifiers;

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIDs($ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withAccountSecrets(array $secrets) {
    $this->accountSecrets = $secrets;
    return $this;
  }

  public function needImages($need) {
    $this->needImages = $need;
    return $this;
  }

  public function needAccountIdentifiers($need) {
    $this->needAccountIdentifiers = $need;
    return $this;
  }

  public function withProviderConfigPHIDs(array $phids) {
    $this->providerConfigPHIDs = $phids;
    return $this;
  }

  public function withRawAccountIdentifiers(array $identifiers) {
    $this->rawAccountIdentifiers = $identifiers;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorExternalAccount();
  }

  protected function willFilterPage(array $accounts) {
    $viewer = $this->getViewer();

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->withPHIDs(mpull($accounts, 'getProviderConfigPHID'))
      ->execute();
    $configs = mpull($configs, null, 'getPHID');

    foreach ($accounts as $key => $account) {
      $config_phid = $account->getProviderConfigPHID();
      $config = idx($configs, $config_phid);

      if (!$config) {
        unset($accounts[$key]);
        continue;
      }

      $account->attachProviderConfig($config);
    }

    if ($this->needImages) {
      $file_phids = mpull($accounts, 'getProfileImagePHID');
      $file_phids = array_filter($file_phids);

      if ($file_phids) {
        // NOTE: We use the omnipotent viewer here because these files are
        // usually created during registration and can't be associated with
        // the correct policies, since the relevant user account does not exist
        // yet. In effect, if you can see an ExternalAccount, you can see its
        // profile image.
        $files = id(new PhabricatorFileQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      $default_file = null;
      foreach ($accounts as $account) {
        $image_phid = $account->getProfileImagePHID();
        if ($image_phid && isset($files[$image_phid])) {
          $account->attachProfileImageFile($files[$image_phid]);
        } else {
          if ($default_file === null) {
            $default_file = PhabricatorFile::loadBuiltin(
              $this->getViewer(),
              'profile.png');
          }
          $account->attachProfileImageFile($default_file);
        }
      }
    }

    if ($this->needAccountIdentifiers) {
      $account_phids = mpull($accounts, 'getPHID');

      $identifiers = id(new PhabricatorExternalAccountIdentifierQuery())
        ->setViewer($viewer)
        ->setParentQuery($this)
        ->withExternalAccountPHIDs($account_phids)
        ->execute();

      $identifiers = mgroup($identifiers, 'getExternalAccountPHID');
      foreach ($accounts as $account) {
        $account_phid = $account->getPHID();
        $account_identifiers = idx($identifiers, $account_phid, array());
        $account->attachAccountIdentifiers($account_identifiers);
      }
    }

    return $accounts;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'account.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'account.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'account.userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->accountSecrets !== null) {
      $where[] = qsprintf(
        $conn,
        'account.accountSecret IN (%Ls)',
        $this->accountSecrets);
    }

    if ($this->providerConfigPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'account.providerConfigPHID IN (%Ls)',
        $this->providerConfigPHIDs);

      // If we have a list of ProviderConfig PHIDs and are joining the
      // identifiers table, also include the list as an additional constraint
      // on the identifiers table.

      // This does not change the query results (an Account and its
      // Identifiers always have the same ProviderConfig PHID) but it allows
      // us to use keys on the Identifier table more efficiently.

      if ($this->shouldJoinIdentifiersTable()) {
        $where[] = qsprintf(
          $conn,
          'identifier.providerConfigPHID IN (%Ls)',
          $this->providerConfigPHIDs);
      }
    }

    if ($this->rawAccountIdentifiers !== null) {
      $hashes = array();

      foreach ($this->rawAccountIdentifiers as $raw_identifier) {
        $hashes[] = PhabricatorHash::digestForIndex($raw_identifier);
      }

      $where[] = qsprintf(
        $conn,
        'identifier.identifierHash IN (%Ls)',
        $hashes);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinIdentifiersTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %R identifier ON account.phid = identifier.externalAccountPHID',
        new PhabricatorExternalAccountIdentifier());
    }

    return $joins;
  }

  protected function shouldJoinIdentifiersTable() {
    return ($this->rawAccountIdentifiers !== null);
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinIdentifiersTable()) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  protected function getPrimaryTableAlias() {
    return 'account';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

}
