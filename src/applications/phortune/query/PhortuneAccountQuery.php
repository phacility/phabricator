<?php

final class PhortuneAccountQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;

  public static function loadAccountsForUser(
    PhabricatorUser $user,
    PhabricatorContentSource $content_source) {

    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withMemberPHIDs(array($user->getPHID()))
      ->execute();

    if (!$accounts) {
      $accounts = array(
        PhortuneAccount::createNewAccount($user, $content_source),
      );
    }

    $accounts = mpull($accounts, null, 'getPHID');

    return $accounts;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMemberPHIDs(array $phids) {
    $this->memberPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhortuneAccount();
  }

  protected function willFilterPage(array $accounts) {
    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(mpull($accounts, 'getPHID'))
      ->withEdgeTypes(
        array(
          PhortuneAccountHasMemberEdgeType::EDGECONST,
          PhortuneAccountHasMerchantEdgeType::EDGECONST,
        ));

    $query->execute();

    foreach ($accounts as $account) {
      $member_phids = $query->getDestinationPHIDs(
        array(
          $account->getPHID(),
        ),
        array(
          PhortuneAccountHasMemberEdgeType::EDGECONST,
        ));
      $member_phids = array_reverse($member_phids);
      $account->attachMemberPHIDs($member_phids);

      $merchant_phids = $query->getDestinationPHIDs(
        array(
          $account->getPHID(),
        ),
        array(
          PhortuneAccountHasMerchantEdgeType::EDGECONST,
        ));
      $merchant_phids = array_reverse($merchant_phids);
      $account->attachMerchantPHIDs($merchant_phids);
    }

    return $accounts;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'a.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'a.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->memberPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'm.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->memberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T m ON a.phid = m.src AND m.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhortuneAccountHasMemberEdgeType::EDGECONST);
    }

    return $joins;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'a';
  }

}
