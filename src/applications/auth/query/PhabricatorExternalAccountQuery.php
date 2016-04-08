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
  private $accountTypes;
  private $accountDomains;
  private $accountIDs;
  private $userPHIDs;
  private $needImages;
  private $accountSecrets;

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withAccountIDs(array $account_ids) {
    $this->accountIDs = $account_ids;
    return $this;
  }

  public function withAccountDomains(array $account_domains) {
    $this->accountDomains = $account_domains;
    return $this;
  }

  public function withAccountTypes(array $account_types) {
    $this->accountTypes = $account_types;
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

  public function newResultObject() {
    return new PhabricatorExternalAccount();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $accounts) {
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

    return $accounts;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'accountType IN (%Ls)',
        $this->accountTypes);
    }

    if ($this->accountDomains !== null) {
      $where[] = qsprintf(
        $conn,
        'accountDomain IN (%Ls)',
        $this->accountDomains);
    }

    if ($this->accountIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'accountID IN (%Ls)',
        $this->accountIDs);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->accountSecrets !== null) {
      $where[] = qsprintf(
        $conn,
        'accountSecret IN (%Ls)',
        $this->accountSecrets);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  /**
   * Attempts to find an external account and if none exists creates a new
   * external account with a shiny new ID and PHID.
   *
   * NOTE: This function assumes the first item in various query parameters is
   * the correct value to use in creating a new external account.
   */
  public function loadOneOrCreate() {
    $account = $this->executeOne();
    if (!$account) {
      $account = new PhabricatorExternalAccount();
      if ($this->accountIDs) {
        $account->setAccountID(reset($this->accountIDs));
      }
      if ($this->accountTypes) {
        $account->setAccountType(reset($this->accountTypes));
      }
      if ($this->accountDomains) {
        $account->setAccountDomain(reset($this->accountDomains));
      }
      if ($this->accountSecrets) {
        $account->setAccountSecret(reset($this->accountSecrets));
      }
      if ($this->userPHIDs) {
        $account->setUserPHID(reset($this->userPHIDs));
      }
      $account->save();
    }
    return $account;
  }

}
