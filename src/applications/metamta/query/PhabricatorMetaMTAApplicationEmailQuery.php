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
    $table  = new PhabricatorMetaMTAApplicationEmail();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T appemail %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildApplicationSearchGroupClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->addresses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'appemail.address IN (%Ls)',
        $this->addresses);
    }

    if ($this->addressPrefix !== null) {
      $where[] = qsprintf(
        $conn_r,
        'appemail.address LIKE %>',
        $this->addressPrefix);
    }

    if ($this->applicationPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'appemail.applicationPHID IN (%Ls)',
        $this->applicationPHIDs);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'appemail.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'appemail.id IN (%Ld)',
        $this->ids);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function getPrimaryTableAlias() {
    return 'appemail';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

}
