<?php

final class PhabricatorBadgesAwardQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $badgePHIDs;
  private $recipientPHIDs;
  private $awarderPHIDs;


  protected function willFilterPage(array $awards) {
    $badges = id(new PhabricatorBadgesQuery())
      ->setViewer($this->getViewer())
      ->withRecipientPHIDs(mpull($awards, null, 'getRecipientPHID'))
      ->execute();

    $badges = mpull($badges, null, 'getPHID');

    foreach ($awards as $key => $award) {
      $award_badge = idx($badges, $award->getBadgePHID());
      if ($award_badge === null) {
        $this->didRejectResult($award);
        unset($awards[$key]);
        continue;
      }

      $award->attachBadge($award_badge);
    }

    return $awards;
  }

  public function withBadgePHIDs(array $phids) {
    $this->badgePHIDs = $phids;
    return $this;
  }

  public function withRecipientPHIDs(array $phids) {
    $this->recipientPHIDs = $phids;
    return $this;
  }

  public function withAwarderPHIDs(array $phids) {
    $this->awarderPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  public function newResultObject() {
    return new PhabricatorBadgesAward();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->badgePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'badgePHID IN (%Ls)',
        $this->badgePHIDs);
    }

    if ($this->recipientPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'recipientPHID IN (%Ls)',
        $this->recipientPHIDs);
    }

    if ($this->awarderPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'awarderPHID IN (%Ls)',
        $this->awarderPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

}
