<?php

final class PassphraseCredentialQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $credentialTypes;
  private $providesTypes;
  private $isDestroyed;
  private $allowConduit;
  private $nameContains;

  private $needSecrets;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withCredentialTypes(array $credential_types) {
    $this->credentialTypes = $credential_types;
    return $this;
  }

  public function withProvidesTypes(array $provides_types) {
    $this->providesTypes = $provides_types;
    return $this;
  }

  public function withIsDestroyed($destroyed) {
    $this->isDestroyed = $destroyed;
    return $this;
  }

  public function withAllowConduit($allow_conduit) {
    $this->allowConduit = $allow_conduit;
    return $this;
  }

  public function withNameContains($name_contains) {
    $this->nameContains = $name_contains;
    return $this;
  }

  public function needSecrets($need_secrets) {
    $this->needSecrets = $need_secrets;
    return $this;
  }

  protected function loadPage() {
    $table = new PassphraseCredential();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $page) {
    if ($this->needSecrets) {
      $secret_ids = mpull($page, 'getSecretID');
      $secret_ids = array_filter($secret_ids);

      $secrets = array();
      if ($secret_ids) {
        $secret_objects = id(new PassphraseSecret())->loadAllWhere(
          'id IN (%Ld)',
          $secret_ids);
        foreach ($secret_objects as $secret) {
          $secret_data = $secret->getSecretData();
          $secrets[$secret->getID()] = new PhutilOpaqueEnvelope($secret_data);
        }
      }

      foreach ($page as $key => $credential) {
        $secret_id = $credential->getSecretID();
        if (!$secret_id) {
          $credential->attachSecret(null);
        } else if (isset($secrets[$secret_id])) {
          $credential->attachSecret($secrets[$secret_id]);
        } else {
          unset($page[$key]);
        }
      }
    }

    return $page;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->credentialTypes) {
      $where[] = qsprintf(
        $conn_r,
        'credentialType in (%Ls)',
        $this->credentialTypes);
    }

    if ($this->providesTypes) {
      $where[] = qsprintf(
        $conn_r,
        'providesType IN (%Ls)',
        $this->providesTypes);
    }

    if ($this->isDestroyed !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isDestroyed = %d',
        (int)$this->isDestroyed);
    }

    if ($this->allowConduit !== null) {
      $where[] = qsprintf(
        $conn_r,
        'allowConduit = %d',
        (int)$this->allowConduit);
    }

    if (strlen($this->nameContains)) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %~',
        $this->nameContains);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPassphraseApplication';
  }

}
