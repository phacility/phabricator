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

  public function newResultObject() {
    return new PassphraseCredential();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    foreach ($page as $key => $credential) {
      $type = PassphraseCredentialType::getTypeByConstant(
        $credential->getCredentialType());
      if (!$type) {
        unset($page[$key]);
        continue;
      }

      $credential->attachImplementation(clone $type);
    }

    return $page;
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

    if ($this->credentialTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'credentialType in (%Ls)',
        $this->credentialTypes);
    }

    if ($this->providesTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'providesType IN (%Ls)',
        $this->providesTypes);
    }

    if ($this->isDestroyed !== null) {
      $where[] = qsprintf(
        $conn,
        'isDestroyed = %d',
        (int)$this->isDestroyed);
    }

    if ($this->allowConduit !== null) {
      $where[] = qsprintf(
        $conn,
        'allowConduit = %d',
        (int)$this->allowConduit);
    }

    if (strlen($this->nameContains)) {
      $where[] = qsprintf(
        $conn,
        'LOWER(name) LIKE %~',
        phutil_utf8_strtolower($this->nameContains));
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPassphraseApplication';
  }

}
