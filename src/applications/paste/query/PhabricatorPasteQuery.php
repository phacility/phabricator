<?php

final class PhabricatorPasteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $parentPHIDs;

  private $needContent;
  private $needRawContent;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  public function withParentPHIDs(array $phids) {
    $this->parentPHIDs = $phids;
    return $this;
  }

  public function needContent($need_content) {
    $this->needContent = $need_content;
    return $this;
  }

  public function needRawContent($need_raw_content) {
    $this->needRawContent = $need_raw_content;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorPaste();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT paste.* FROM %T paste %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $pastes = $table->loadAllFromArray($data);

    if ($pastes && $this->needRawContent) {
      $this->loadRawContent($pastes);
    }

    if ($pastes && $this->needContent) {
      $this->loadContent($pastes);
    }

    return $pastes;
  }

  protected function buildWhereClause($conn_r) {
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

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->parentPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'parentPHID IN (%Ls)',
        $this->parentPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function getContentCacheKey(PhabricatorPaste $paste) {
    return 'P'.$paste->getID().':content/'.$paste->getLanguage();
  }

  private function loadRawContent(array $pastes) {
    $file_phids = mpull($pastes, 'getFilePHID');
    $files = id(new PhabricatorFile())->loadAllWhere(
      'phid IN (%Ls)',
      $file_phids);
    $files = mpull($files, null, 'getPHID');

    foreach ($pastes as $paste) {
      $file = idx($files, $paste->getFilePHID());
      if ($file) {
        $paste->attachRawContent($file->loadFileData());
      } else {
        $paste->attachRawContent('');
      }
    }
  }

  private function loadContent(array $pastes) {
    $cache = new PhabricatorKeyValueDatabaseCache();

    $cache = new PhutilKeyValueCacheProfiler($cache);
    $cache->setProfiler(PhutilServiceProfiler::getInstance());

    $keys = array();
    foreach ($pastes as $paste) {
      $keys[] = $this->getContentCacheKey($paste);
    }


    $caches = $cache->getKeys($keys);

    $need_raw = array();
    foreach ($pastes as $paste) {
      $key = $this->getContentCacheKey($paste);
      if (isset($caches[$key])) {
        $paste->attachContent(phutil_safe_html($caches[$key]));
      } else {
        $need_raw[] = $paste;
      }
    }

    if (!$need_raw) {
      return;
    }

    $write_data = array();

    $this->loadRawContent($need_raw);
    foreach ($need_raw as $paste) {
      $content = $this->buildContent($paste);
      $paste->attachContent($content);

      $write_data[$this->getContentCacheKey($paste)] = (string)$content;
    }

    $cache->setKeys($write_data);
  }


  private function buildContent(PhabricatorPaste $paste) {
    $language = $paste->getLanguage();
    $source = $paste->getRawContent();

    if (empty($language)) {
      return PhabricatorSyntaxHighlighter::highlightWithFilename(
        $paste->getTitle(),
        $source);
    } else {
      return PhabricatorSyntaxHighlighter::highlightWithLanguage(
        $language,
        $source);
    }
  }

}
