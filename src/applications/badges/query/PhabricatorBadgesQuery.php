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
      $query = id(new PhabricatorBadgesAwardQuery())
        ->setViewer($this->getViewer())
        ->withBadgePHIDs(mpull($badges, 'getPHID'))
        ->execute();

      $awards = mgroup($query, 'getBadgePHID');

      foreach ($badges as $badge) {
        $badge_awards = idx($awards, $badge->getPHID(), array());
        $badge->attachAwards($badge_awards);
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

  public function getBuiltinOrders() {
    return array(
      'quality' => array(
        'vector' => array('quality', 'id'),
        'name' => pht('Rarity (Rarest First)'),
      ),
      'shoddiness' => array(
        'vector' => array('-quality', '-id'),
        'name' => pht('Rarity (Most Common First)'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return array(
      'quality' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'quality',
        'reverse' => true,
        'type' => 'int',
      ),
    ) + parent::getOrderableColumns();
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $badge = $this->loadCursorObject($cursor);
    return array(
      'quality' => $badge->getQuality(),
      'id' => $badge->getID(),
    );
  }

}
