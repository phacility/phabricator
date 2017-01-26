<?php

final class PhabricatorPasteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $parentPHIDs;

  private $needContent;
  private $needRawContent;
  private $needSnippets;
  private $languages;
  private $includeNoLanguage;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $statuses;


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

  public function needSnippets($need_snippets) {
    $this->needSnippets = $need_snippets;
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

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
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

    if ($this->needSnippets) {
      $pastes = $this->loadSnippets($pastes);
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

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
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
        PhabricatorHash::digestForIndex($paste->getTitle()),
      ));
  }

  private function getSnippetCacheKey(PhabricatorPaste $paste) {
    return implode(
      ':',
      array(
        'P'.$paste->getID(),
        $paste->getFilePHID(),
        $paste->getLanguage(),
        'snippet',
        'v2',
        PhabricatorHash::digestForIndex($paste->getTitle()),
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

  private function loadSnippets(array $pastes) {
    $cache = new PhabricatorKeyValueDatabaseCache();

    $cache = new PhutilKeyValueCacheProfiler($cache);
    $cache->setProfiler(PhutilServiceProfiler::getInstance());

    $keys = array();
    foreach ($pastes as $paste) {
      $keys[] = $this->getSnippetCacheKey($paste);
    }

    $caches = $cache->getKeys($keys);

    $need_raw = array();
    $have_cache = array();
    foreach ($pastes as $paste) {
      $key = $this->getSnippetCacheKey($paste);
      if (isset($caches[$key])) {
        $snippet_data = phutil_json_decode($caches[$key], true);
        $snippet = new PhabricatorPasteSnippet(
          phutil_safe_html($snippet_data['content']),
          $snippet_data['type'],
          $snippet_data['contentLineCount']);
        $paste->attachSnippet($snippet);
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

      $snippet = $this->buildSnippet($paste);
      $paste->attachSnippet($snippet);
      $snippet_data = array(
        'content' => (string)$snippet->getContent(),
        'type' => (string)$snippet->getType(),
        'contentLineCount' => $snippet->getContentLineCount(),
      );
      $write_data[$this->getSnippetCacheKey($paste)] = phutil_json_encode(
        $snippet_data);
    }

    if ($write_data) {
      $cache->setKeys($write_data);
    }

    return $pastes;
  }

  private function buildContent(PhabricatorPaste $paste) {
    return $this->highlightSource(
      $paste->getRawContent(),
      $paste->getTitle(),
      $paste->getLanguage());
  }

  private function buildSnippet(PhabricatorPaste $paste) {
    $snippet_type = PhabricatorPasteSnippet::FULL;
    $snippet = $paste->getRawContent();

    if (strlen($snippet) > 1024) {
      $snippet_type = PhabricatorPasteSnippet::FIRST_BYTES;
      $snippet = id(new PhutilUTF8StringTruncator())
        ->setMaximumBytes(1024)
        ->setTerminator('')
        ->truncateString($snippet);
    }

    $lines = phutil_split_lines($snippet);
    $line_count = count($lines);
    if ($line_count > 5) {
      $snippet_type = PhabricatorPasteSnippet::FIRST_LINES;
      $snippet = implode('', array_slice($lines, 0, 5));
    }

    return new PhabricatorPasteSnippet(
      $this->highlightSource(
        $snippet,
        $paste->getTitle(),
        $paste->getLanguage()),
      $snippet_type,
      $line_count);
  }

  private function highlightSource($source, $title, $language) {
      if (empty($language)) {
        return PhabricatorSyntaxHighlighter::highlightWithFilename(
          $title,
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
