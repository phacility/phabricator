<?php

final class PhabricatorBadgesAwardQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $badgePHIDs;
  private $recipientPHIDs;
  private $awarderPHIDs;
  private $badgeStatuses = null;

  protected function willFilterPage(array $awards) {
    $badge_phids = array();
    foreach ($awards as $key => $award) {
      $badge_phids[] = $award->getBadgePHID();
    }

    $badges = id(new PhabricatorBadgesQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($badge_phids)
      ->execute();

    $badges = mpull($badges, null, 'getPHID');
    foreach ($awards as $key => $award) {
      $award_badge = idx($badges, $award->getBadgePHID());
      if (!$award_badge) {
        unset($awards[$key]);
        $this->didRejectResult($award);
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

  public function withBadgeStatuses(array $statuses) {
    $this->badgeStatuses = $statuses;
    return $this;
  }

  private function shouldJoinBadge() {
    return (bool)$this->badgeStatuses;
  }

  public function newResultObject() {
    return new PhabricatorBadgesAward();
  }

  protected function getPrimaryTableAlias() {
    return 'badges_award';
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->badgePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'badges_award.badgePHID IN (%Ls)',
        $this->badgePHIDs);
    }

    if ($this->recipientPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'badges_award.recipientPHID IN (%Ls)',
        $this->recipientPHIDs);
    }

    if ($this->awarderPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'badges_award.awarderPHID IN (%Ls)',
        $this->awarderPHIDs);
    }

    if ($this->badgeStatuses !== null) {
      $where[] = qsprintf(
        $conn,
        'badges_badge.status IN (%Ls)',
        $this->badgeStatuses);
    }


    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $join = parent::buildJoinClauseParts($conn);
    $badges = new PhabricatorBadgesBadge();

    if ($this->shouldJoinBadge()) {
      $join[] = qsprintf(
        $conn,
        'JOIN %T badges_badge ON badges_award.badgePHID = badges_badge.phid',
        $badges->getTableName());
    }

    return $join;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

}
