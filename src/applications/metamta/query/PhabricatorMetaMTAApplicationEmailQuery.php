<?php

final class PhabricatorMetaMTAApplicationEmailQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $addresses;
  private $addressPrefix;
  private $applicationPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAddresses(array $addresses) {
    $this->addresses = $addresses;
    return $this;
  }

  public function withAddressPrefix($prefix) {
    $this->addressPrefix = $prefix;
    return $this;
  }

  public function withApplicationPHIDs(array $phids) {
    $this->applicationPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PhabricatorMetaMTAApplicationEmail());
  }

  protected function willFilterPage(array $app_emails) {
    $app_emails_map = mgroup($app_emails, 'getApplicationPHID');
    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array_keys($app_emails_map))
      ->execute();
    $applications = mpull($applications, null, 'getPHID');

    foreach ($app_emails_map as $app_phid => $app_emails_group) {
      foreach ($app_emails_group as $app_email) {
        $application = idx($applications, $app_phid);
        if (!$application) {
          unset($app_emails[$app_phid]);
          continue;
        }
        $app_email->attachApplication($application);
      }
    }
    return $app_emails;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->addresses !== null) {
      $where[] = qsprintf(
        $conn,
        'appemail.address IN (%Ls)',
        $this->addresses);
    }

    if ($this->addressPrefix !== null) {
      $where[] = qsprintf(
        $conn,
        'appemail.address LIKE %>',
        $this->addressPrefix);
    }

    if ($this->applicationPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'appemail.applicationPHID IN (%Ls)',
        $this->applicationPHIDs);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'appemail.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'appemail.id IN (%Ld)',
        $this->ids);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'appemail';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

}
