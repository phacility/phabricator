<?php

final class PhabricatorPasteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $parentPHIDs;

  private $needContent;
  private $needRawContent;
  private $languages;
  private $includeNoLanguage;
  private $dateCreatedAfter;
  private $dateCreatedBefore;

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

  public function withLanguages(array $languages) {
    $this->includeNoLanguage = false;
    foreach ($languages as $key => $language) {
      if ($language === null) {
        $languages[$key] = '';
        continue;
      }
    }
    $this->languages = $languages;
    return $this;
  }

  public function withDateCreatedBefore($date_created_before) {
    $this->dateCreatedBefore = $date_created_before;
    return $this;
  }

  public function withDateCreatedAfter($date_created_after) {
    $this->dateCreatedAfter = $date_created_after;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorPaste();
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PhabricatorPaste());
  }

  protected function didFilterPage(array $pastes) {
    if ($this->needRawContent) {
      $pastes = $this->loadRawContent($pastes);
    }

    if ($this->needContent) {
      $pastes = $this->loadContent($pastes);
    }

    return $pastes;
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

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->parentPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'parentPHID IN (%Ls)',
        $this->parentPHIDs);
    }

    if ($this->languages !== null) {
      $where[] = qsprintf(
        $conn,
        'language IN (%Ls)',
        $this->languages);
    }

    if ($this->dateCreatedAfter !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    return $where;
  }

  private function getContentCacheKey(PhabricatorPaste $paste) {
    return implode(
      ':',
      array(
        'P'.$paste->getID(),
        $paste->getFilePHID(),
        $paste->getLanguage(),
      ));
  }

  private function loadRawContent(array $pastes) {
    $file_phids = mpull($pastes, 'getFilePHID');
    $files = id(new PhabricatorFileQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($file_phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    foreach ($pastes as $key => $paste) {
      $file = idx($files, $paste->getFilePHID());
      if (!$file) {
        unset($pastes[$key]);
        continue;
      }
      try {
        $paste->attachRawContent($file->loadFileData());
      } catch (Exception $ex) {
        // We can hit various sorts of file storage issues here. Just drop the
        // paste if the file is dead.
        unset($pastes[$key]);
        continue;
      }
    }

    return $pastes;
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
    $have_cache = array();
    foreach ($pastes as $paste) {
      $key = $this->getContentCacheKey($paste);
      if (isset($caches[$key])) {
        $paste->attachContent(phutil_safe_html($caches[$key]));
        $have_cache[$paste->getPHID()] = true;
      } else {
        $need_raw[$key] = $paste;
      }
    }

    if (!$need_raw) {
      return $pastes;
    }

    $write_data = array();

    $have_raw = $this->loadRawContent($need_raw);
    $have_raw = mpull($have_raw, null, 'getPHID');
    foreach ($pastes as $key => $paste) {
      $paste_phid = $paste->getPHID();
      if (isset($have_cache[$paste_phid])) {
        continue;
      }

      if (empty($have_raw[$paste_phid])) {
        unset($pastes[$key]);
        continue;
      }

      $content = $this->buildContent($paste);
      $paste->attachContent($content);
      $write_data[$this->getContentCacheKey($paste)] = (string)$content;
    }

    if ($write_data) {
      $cache->setKeys($write_data);
    }

    return $pastes;
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

  public function getQueryApplicationClass() {
    return 'PhabricatorPasteApplication';
  }

}
