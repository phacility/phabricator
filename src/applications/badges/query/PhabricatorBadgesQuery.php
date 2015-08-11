<?php

final class PhabricatorBadgesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $qualities;
  private $statuses;
  private $recipientPHIDs;

  private $needRecipients;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withQualities(array $qualities) {
    $this->qualities = $qualities;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withRecipientPHIDs(array $recipient_phids) {
    $this->recipientPHIDs = $recipient_phids;
    return $this;
  }

  public function needRecipients($need_recipients) {
    $this->needRecipients = $need_recipients;
    return $this;
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  public function newResultObject() {
    return new PhabricatorBadgesBadge();
  }

  protected function didFilterPage(array $badges) {

    if ($this->needRecipients) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($badges, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorBadgeHasRecipientEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      foreach ($badges as $badge) {
        $phids = $edge_query->getDestinationPHIDs(
          array(
            $badge->getPHID(),
          ));
        $badge->attachRecipientPHIDs($phids);
      }
    }

    return $badges;
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

    if ($this->qualities !== null) {
      $where[] = qsprintf(
        $conn,
        'quality IN (%Ls)',
        $this->qualities);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

}
