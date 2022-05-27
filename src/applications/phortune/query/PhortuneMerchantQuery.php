<?php

final class PhortuneMerchantQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;
  private $needProfileImage;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }

  public function needProfileImage($need) {
    $this->needProfileImage = $need;
    return $this;
  }

  public function newResultObject() {
    return new PhortuneMerchant();
  }

  protected function willFilterPage(array $merchants) {
    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(mpull($merchants, 'getPHID'))
      ->withEdgeTypes(array(PhortuneMerchantHasMemberEdgeType::EDGECONST));
    $query->execute();

    foreach ($merchants as $merchant) {
      $member_phids = $query->getDestinationPHIDs(array($merchant->getPHID()));
      $member_phids = array_reverse($member_phids);
      $merchant->attachMemberPHIDs($member_phids);
    }

    if ($this->needProfileImage) {
      $default = null;
      $file_phids = mpull($merchants, 'getProfileImagePHID');
      $file_phids = array_filter($file_phids);
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setParentQuery($this)
          ->setViewer($this->getViewer())
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      foreach ($merchants as $merchant) {
        $file = idx($files, $merchant->getProfileImagePHID());
        if (!$file) {
          if (!$default) {
            $default = PhabricatorFile::loadBuiltin(
              $this->getViewer(),
              'merchant.png');
          }
          $file = $default;
        }
        $merchant->attachProfileImageFile($file);
      }
    }

    return $merchants;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'merchant.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'merchant.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->memberPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'e.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->memberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T e ON merchant.phid = e.src AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhortuneMerchantHasMemberEdgeType::EDGECONST);
    }

    return $joins;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'merchant';
  }

  public static function canViewersEditMerchants(
    array $viewer_phids,
    array $merchant_phids) {

    // See T13366 for some discussion. This is an unusual caching construct to
    // make policy filtering of Accounts easier.

    foreach ($viewer_phids as $key => $viewer_phid) {
      if (!$viewer_phid) {
        unset($viewer_phids[$key]);
      }
    }

    if (!$viewer_phids) {
      return array();
    }

    $cache_key = 'phortune.merchant.can-edit';
    $cache = PhabricatorCaches::getRequestCache();

    $cache_data = $cache->getKey($cache_key);
    if (!$cache_data) {
      $cache_data = array();
    }

    $load_phids = array();
    foreach ($viewer_phids as $viewer_phid) {
      if (!isset($cache_data[$viewer_phid])) {
        $load_phids[] = $viewer_phid;
      }
    }

    $did_write = false;
    foreach ($load_phids as $load_phid) {
      $merchants = id(new self())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withMemberPHIDs(array($load_phid))
        ->execute();
      foreach ($merchants as $merchant) {
        $cache_data[$load_phid][$merchant->getPHID()] = true;
        $did_write = true;
      }
    }

    if ($did_write) {
      $cache->setKey($cache_key, $cache_data);
    }

    $results = array();
    foreach ($viewer_phids as $viewer_phid) {
      foreach ($merchant_phids as $merchant_phid) {
        if (!isset($cache_data[$viewer_phid][$merchant_phid])) {
          continue;
        }
        $results[$viewer_phid][$merchant_phid] = true;
      }
    }

    return $results;
  }

}
