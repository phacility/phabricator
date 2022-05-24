<?php

final class HeraldWebhookRequestQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $webhookPHIDs;
  private $lastRequestEpochMin;
  private $lastRequestEpochMax;
  private $lastRequestResults;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withWebhookPHIDs(array $phids) {
    $this->webhookPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new HeraldWebhookRequest();
  }

  public function withLastRequestEpochBetween($epoch_min, $epoch_max) {
    $this->lastRequestEpochMin = $epoch_min;
    $this->lastRequestEpochMax = $epoch_max;
    return $this;
  }

  public function withLastRequestResults(array $results) {
    $this->lastRequestResults = $results;
    return $this;
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

    if ($this->webhookPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'webhookPHID IN (%Ls)',
        $this->webhookPHIDs);
    }

    if ($this->lastRequestEpochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'lastRequestEpoch >= %d',
        $this->lastRequestEpochMin);
    }

    if ($this->lastRequestEpochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'lastRequestEpoch <= %d',
        $this->lastRequestEpochMax);
    }

    if ($this->lastRequestResults !== null) {
      $where[] = qsprintf(
        $conn,
        'lastRequestResult IN (%Ls)',
        $this->lastRequestResults);
    }

    return $where;
  }

  protected function willFilterPage(array $requests) {
    $hook_phids = mpull($requests, 'getWebhookPHID');

    $hooks = id(new HeraldWebhookQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($hook_phids)
      ->execute();
    $hooks = mpull($hooks, null, 'getPHID');

    foreach ($requests as $key => $request) {
      $hook_phid = $request->getWebhookPHID();
      $hook = idx($hooks, $hook_phid);

      if (!$hook) {
        unset($requests[$key]);
        $this->didRejectResult($request);
        continue;
      }

      $request->attachWebhook($hook);
    }

    return $requests;
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

}
