<?php

final class DifferentialChangesetParser {

  protected $visible      = array();
  protected $new          = array();
  protected $old          = array();
  protected $intra        = array();
  protected $newRender    = null;
  protected $oldRender    = null;

  protected $filename     = null;
  protected $hunkStartLines = array();

  protected $comments     = array();
  protected $specialAttributes = array();

  protected $changeset;
  protected $whitespaceMode = null;

  protected $renderCacheKey = null;

  private $handles = array();
  private $user;

  private $leftSideChangesetID;
  private $leftSideAttachesToNewFile;

  private $rightSideChangesetID;
  private $rightSideAttachesToNewFile;

  private $originalLeft;
  private $originalRight;

  private $renderingReference;
  private $isSubparser;

  private $isTopLevel;
  private $coverage;
  private $markupEngine;
  private $highlightErrors;
  private $disableCache;
  private $renderer;

  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    if (!$this->renderer) {
      return new DifferentialChangesetTwoUpRenderer();
    }
    return $this->renderer;
  }

  public function setDisableCache($disable_cache) {
    $this->disableCache = $disable_cache;
    return $this;
  }

  public function getDisableCache() {
    return $this->disableCache;
  }

  const CACHE_VERSION = 11;
  const CACHE_MAX_SIZE = 8e6;

  const ATTR_GENERATED  = 'attr:generated';
  const ATTR_DELETED    = 'attr:deleted';
  const ATTR_UNCHANGED  = 'attr:unchanged';
  const ATTR_WHITELINES = 'attr:white';

  const LINES_CONTEXT = 8;

  const WHITESPACE_SHOW_ALL         = 'show-all';
  const WHITESPACE_IGNORE_TRAILING  = 'ignore-trailing';

  // TODO: This is now "Ignore Most" in the UI.
  const WHITESPACE_IGNORE_ALL       = 'ignore-all';

  const WHITESPACE_IGNORE_FORCE     = 'ignore-force';

  public function setOldLines(array $lines) {
    $this->old = $lines;
    return $this;
  }

  public function setNewLines(array $lines) {
    $this->new = $lines;
    return $this;
  }

  public function setSpecialAttributes(array $attributes) {
    $this->specialAttributes = $attributes;
    return $this;
  }

  public function setIntraLineDiffs(array $diffs) {
    $this->intra = $diffs;
    return $this;
  }

  public function setVisibileLinesMask(array $mask) {
    $this->visible = $mask;
    return $this;
  }

  /**
   * Configure which Changeset comments added to the right side of the visible
   * diff will be attached to. The ID must be the ID of a real Differential
   * Changeset.
   *
   * The complexity here is that we may show an arbitrary side of an arbitrary
   * changeset as either the left or right part of a diff. This method allows
   * the left and right halves of the displayed diff to be correctly mapped to
   * storage changesets.
   *
   * @param id    The Differential Changeset ID that comments added to the right
   *              side of the visible diff should be attached to.
   * @param bool  If true, attach new comments to the right side of the storage
   *              changeset. Note that this may be false, if the left side of
   *              some storage changeset is being shown as the right side of
   *              a display diff.
   * @return this
   */
  public function setRightSideCommentMapping($id, $is_new) {
    $this->rightSideChangesetID = $id;
    $this->rightSideAttachesToNewFile = $is_new;
    return $this;
  }

  /**
   * See setRightSideCommentMapping(), but this sets information for the left
   * side of the display diff.
   */
  public function setLeftSideCommentMapping($id, $is_new) {
    $this->leftSideChangesetID = $id;
    $this->leftSideAttachesToNewFile = $is_new;
    return $this;
  }

  public function setOriginals(
    DifferentialChangeset $left,
    DifferentialChangeset $right) {

    $this->originalLeft = $left;
    $this->originalRight = $right;
  }

  public function diffOriginals() {
    $engine = new PhabricatorDifferenceEngine();
    $changeset = $engine->generateChangesetFromFileContent(
      implode('', mpull($this->originalLeft->getHunks(), 'getChanges')),
      implode('', mpull($this->originalRight->getHunks(), 'getChanges')));

    $parser = new DifferentialHunkParser();

    return $parser->parseHunksForHighlightMasks(
      $changeset->getHunks(),
      $this->originalLeft->getHunks(),
      $this->originalRight->getHunks());
  }

  /**
   * Set a key for identifying this changeset in the render cache. If set, the
   * parser will attempt to use the changeset render cache, which can improve
   * performance for frequently-viewed changesets.
   *
   * By default, there is no render cache key and parsers do not use the cache.
   * This is appropriate for rarely-viewed changesets.
   *
   * NOTE: Currently, this key must be a valid Differential Changeset ID.
   *
   * @param   string  Key for identifying this changeset in the render cache.
   * @return  this
   */
  public function setRenderCacheKey($key) {
    $this->renderCacheKey = $key;
    return $this;
  }

  private function getRenderCacheKey() {
    return $this->renderCacheKey;
  }

  public function setChangeset(DifferentialChangeset $changeset) {
    $this->changeset = $changeset;

    $this->setFilename($changeset->getFilename());

    return $this;
  }

  public function setWhitespaceMode($whitespace_mode) {
    $this->whitespaceMode = $whitespace_mode;
    return $this;
  }

  public function setRenderingReference($ref) {
    $this->renderingReference = $ref;
    return $this;
  }

  private function getRenderingReference() {
    return $this->renderingReference;
  }

  public function getChangeset() {
    return $this->changeset;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }
  private function getCoverage() {
    return $this->coverage;
  }

  public function parseInlineComment(
    PhabricatorInlineCommentInterface $comment) {

    // Parse only comments which are actually visible.
    if ($this->isCommentVisibleOnRenderedDiff($comment)) {
      $this->comments[] = $comment;
    }
    return $this;
  }

  private function loadCache() {
    $render_cache_key = $this->getRenderCacheKey();
    if (!$render_cache_key) {
      return false;
    }

    $data = null;

    $changeset = new DifferentialChangeset();
    $conn_r = $changeset->establishConnection('r');
    $data = queryfx_one(
      $conn_r,
      'SELECT * FROM %T WHERE id = %d',
      $changeset->getTableName().'_parse_cache',
      $render_cache_key);

    if (!$data) {
      return false;
    }

    if ($data['cache'][0] == '{') {
      // This is likely an old-style JSON cache which we will not be able to
      // deserialize.
      return false;
    }

    $data = unserialize($data['cache']);
    if (!is_array($data) || !$data) {
      return false;
    }

    foreach (self::getCacheableProperties() as $cache_key) {
      if (!array_key_exists($cache_key, $data)) {
        // If we're missing a cache key, assume we're looking at an old cache
        // and ignore it.
        return false;
      }
    }

    if ($data['cacheVersion'] !== self::CACHE_VERSION) {
      return false;
    }

    // Someone displays contents of a partially cached shielded file.
    if (!isset($data['newRender']) && (!$this->isTopLevel || $this->comments)) {
      return false;
    }

    unset($data['cacheVersion'], $data['cacheHost']);
    $cache_prop = array_select_keys($data, self::getCacheableProperties());
    foreach ($cache_prop as $cache_key => $v) {
      $this->$cache_key = $v;
    }

    return true;
  }

  protected static function getCacheableProperties() {
    return array(
      'visible',
      'new',
      'old',
      'intra',
      'newRender',
      'oldRender',
      'specialAttributes',
      'hunkStartLines',
      'cacheVersion',
      'cacheHost',
    );
  }

  public function saveCache() {
    if ($this->highlightErrors) {
      return false;
    }

    $render_cache_key = $this->getRenderCacheKey();
    if (!$render_cache_key) {
      return false;
    }

    $cache = array();
    foreach (self::getCacheableProperties() as $cache_key) {
      switch ($cache_key) {
        case 'cacheVersion':
          $cache[$cache_key] = self::CACHE_VERSION;
          break;
        case 'cacheHost':
          $cache[$cache_key] = php_uname('n');
          break;
        default:
          $cache[$cache_key] = $this->$cache_key;
          break;
      }
    }
    $cache = serialize($cache);

    // We don't want to waste too much space by a single changeset.
    if (strlen($cache) > self::CACHE_MAX_SIZE) {
      return;
    }

    try {
      $changeset = new DifferentialChangeset();
      $conn_w = $changeset->establishConnection('w');

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      queryfx(
        $conn_w,
        'INSERT INTO %T (id, cache, dateCreated) VALUES (%d, %s, %d)
          ON DUPLICATE KEY UPDATE cache = VALUES(cache)',
        DifferentialChangeset::TABLE_CACHE,
        $render_cache_key,
        $cache,
        time());
    } catch (AphrontQueryException $ex) {
      // TODO: uhoh
    }
  }

  private function markGenerated($new_corpus_block = '') {
    $generated_guess = (strpos($new_corpus_block, '@'.'generated') !== false);

    if (!$generated_guess) {
      $generated_path_regexps = PhabricatorEnv::getEnvConfig(
        'differential.generated-paths');
      foreach ($generated_path_regexps as $regexp) {
        if (preg_match($regexp, $this->changeset->getFilename())) {
          $generated_guess = true;
          break;
        }
      }
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_DIFFERENTIAL_WILLMARKGENERATED,
      array(
        'corpus' => $new_corpus_block,
        'is_generated' => $generated_guess,
      )
    );
    PhutilEventEngine::dispatchEvent($event);

    $generated = $event->getValue('is_generated');
    $this->specialAttributes[self::ATTR_GENERATED] = $generated;
  }

  public function isGenerated() {
    return idx($this->specialAttributes, self::ATTR_GENERATED, false);
  }

  public function isDeleted() {
    return idx($this->specialAttributes, self::ATTR_DELETED, false);
  }

  public function isUnchanged() {
    return idx($this->specialAttributes, self::ATTR_UNCHANGED, false);
  }

  public function isWhitespaceOnly() {
    return idx($this->specialAttributes, self::ATTR_WHITELINES, false);
  }

  private function applyIntraline(&$render, $intra, $corpus) {

    foreach ($render as $key => $text) {
      if (isset($intra[$key])) {
        $render[$key] = ArcanistDiffUtils::applyIntralineDiff(
          $text,
          $intra[$key]);
      }
    }
  }

  private function getHighlightFuture($corpus) {
    return $this->highlightEngine->getHighlightFuture(
      $this->highlightEngine->getLanguageFromFilename($this->filename),
      $corpus);
  }

  protected function processHighlightedSource($data, $result) {

    $result_lines = phutil_split_lines($result);
    foreach ($data as $key => $info) {
      if (!$info) {
        unset($result_lines[$key]);
      }
    }
    return $result_lines;
  }

  private function tryCacheStuff() {
    $whitespace_mode = $this->whitespaceMode;
    switch ($whitespace_mode) {
      case self::WHITESPACE_SHOW_ALL:
      case self::WHITESPACE_IGNORE_TRAILING:
      case self::WHITESPACE_IGNORE_FORCE:
        break;
      default:
        $whitespace_mode = self::WHITESPACE_IGNORE_ALL;
        break;
    }

    $skip_cache = ($whitespace_mode != self::WHITESPACE_IGNORE_ALL);
    if ($this->disableCache) {
      $skip_cache = true;
    }

    $this->whitespaceMode = $whitespace_mode;

    $changeset = $this->changeset;

    if ($changeset->getFileType() != DifferentialChangeType::FILE_TEXT &&
        $changeset->getFileType() != DifferentialChangeType::FILE_SYMLINK) {

      $this->markGenerated();

    } else {
      if ($skip_cache || !$this->loadCache()) {
        $this->process();
        if (!$skip_cache) {
          $this->saveCache();
        }
      }
    }
  }

  private function process() {
    $whitespace_mode = $this->whitespaceMode;
    $changeset = $this->changeset;

    $ignore_all = (($whitespace_mode == self::WHITESPACE_IGNORE_ALL) ||
                  ($whitespace_mode == self::WHITESPACE_IGNORE_FORCE));

    $force_ignore = ($whitespace_mode == self::WHITESPACE_IGNORE_FORCE);

    if (!$force_ignore) {
      if ($ignore_all && $changeset->getWhitespaceMatters()) {
        $ignore_all = false;
      }
    }

    // The "ignore all whitespace" algorithm depends on rediffing the
    // files, and we currently need complete representations of both
    // files to do anything reasonable. If we only have parts of the files,
    // don't use the "ignore all" algorithm.
    if ($ignore_all) {
      $hunks = $changeset->getHunks();
      if (count($hunks) !== 1) {
        $ignore_all = false;
      } else {
        $first_hunk = reset($hunks);
        if ($first_hunk->getOldOffset() != 1 ||
            $first_hunk->getNewOffset() != 1) {
            $ignore_all = false;
        }
      }
    }

    if ($ignore_all) {
      $old_file = $changeset->makeOldFile();
      $new_file = $changeset->makeNewFile();
      if ($old_file == $new_file) {
        // If the old and new files are exactly identical, the synthetic
        // diff below will give us nonsense and whitespace modes are
        // irrelevant anyway. This occurs when you, e.g., copy a file onto
        // itself in Subversion (see T271).
        $ignore_all = false;
      }
    }

    $hunk_parser = new DifferentialHunkParser();
    $hunk_parser->setWhitespaceMode($whitespace_mode);
    $hunk_parser->parseHunksForLineData($changeset->getHunks());

    // Depending on the whitespace mode, we may need to compute a different
    // set of changes than the set of changes in the hunk data (specificaly,
    // we might want to consider changed lines which have only whitespace
    // changes as unchanged).
    if ($ignore_all) {
      $engine = new PhabricatorDifferenceEngine();
      $engine->setIgnoreWhitespace(true);
      $no_whitespace_changeset = $engine->generateChangesetFromFileContent(
        $old_file,
        $new_file);

      $type_parser = new DifferentialHunkParser();
      $type_parser->parseHunksForLineData($no_whitespace_changeset->getHunks());

      $hunk_parser->setOldLineTypeMap($type_parser->getOldLineTypeMap());
      $hunk_parser->setNewLineTypeMap($type_parser->getNewLineTypeMap());
    }

    $hunk_parser->reparseHunksForSpecialAttributes();

    $unchanged = false;
    if (!$hunk_parser->getHasAnyChanges()) {
      $filetype = $this->changeset->getFileType();
      if ($filetype == DifferentialChangeType::FILE_TEXT ||
          $filetype == DifferentialChangeType::FILE_SYMLINK) {
        $unchanged = true;
      }
    }

    $changetype = $this->changeset->getChangeType();
    if ($changetype == DifferentialChangeType::TYPE_MOVE_AWAY) {
      // sometimes we show moved files as unchanged, sometimes deleted,
      // and sometimes inconsistent with what actually happened at the
      // destination of the move.  Rather than make a false claim,
      // omit the 'not changed' notice if this is the source of a move
      $unchanged = false;
    }

    $this->setSpecialAttributes(array(
      self::ATTR_UNCHANGED  => $unchanged,
      self::ATTR_DELETED    => $hunk_parser->getIsDeleted(),
      self::ATTR_WHITELINES => !$hunk_parser->getHasTextChanges(),
    ));

    $hunk_parser->generateIntraLineDiffs();
    $hunk_parser->generateVisibileLinesMask();

    $this->setOldLines($hunk_parser->getOldLines());
    $this->setNewLines($hunk_parser->getNewLines());
    $this->setIntraLineDiffs($hunk_parser->getIntraLineDiffs());
    $this->setVisibileLinesMask($hunk_parser->getVisibleLinesMask());
    $this->hunkStartLines = $hunk_parser->getHunkStartLines(
      $changeset->getHunks());

    $new_corpus = $hunk_parser->getNewCorpus();
    $new_corpus_block = implode('', $new_corpus);
    $this->markGenerated($new_corpus_block);

    if ($this->isTopLevel &&
        !$this->comments &&
          ($this->isGenerated() ||
           $this->isUnchanged() ||
           $this->isDeleted())) {
      return;
    }

    $old_corpus = $hunk_parser->getOldCorpus();
    $old_corpus_block = implode('', $old_corpus);
    $old_future = $this->getHighlightFuture($old_corpus_block);
    $new_future = $this->getHighlightFuture($new_corpus_block);
    $futures = array(
      'old' => $old_future,
      'new' => $new_future,
    );
    $corpus_blocks = array(
      'old' => $old_corpus_block,
      'new' => $new_corpus_block,
    );

    $this->highlightErrors = false;
    foreach (Futures($futures) as $key => $future) {
      try {
        try {
          $highlighted = $future->resolve();
        } catch (PhutilSyntaxHighlighterException $ex) {
          $this->highlightErrors = true;
          $highlighted = id(new PhutilDefaultSyntaxHighlighter())
            ->getHighlightFuture($corpus_blocks[$key])
            ->resolve();
        }
        switch ($key) {
        case 'old':
          $this->oldRender = $this->processHighlightedSource(
            $this->old,
            $highlighted);
          break;
        case 'new':
          $this->newRender = $this->processHighlightedSource(
            $this->new,
            $highlighted);
          break;
        }
      } catch (Exception $ex) {
        phlog($ex);
        throw $ex;
      }
    }

    $this->applyIntraline(
      $this->oldRender,
      ipull($this->intra, 0),
      $old_corpus);
    $this->applyIntraline(
      $this->newRender,
      ipull($this->intra, 1),
      $new_corpus);
  }

  private function shouldRenderPropertyChangeHeader($changeset) {
    if (!$this->isTopLevel) {
      // We render properties only at top level; otherwise we get multiple
      // copies of them when a user clicks "Show More".
      return false;
    }

    $old = $changeset->getOldProperties();
    $new = $changeset->getNewProperties();

    if ($old === $new) {
      return false;
    }

    if ($changeset->getChangeType() == DifferentialChangeType::TYPE_ADD &&
        $new == array('unix:filemode' => '100644')) {
      return false;
    }

    if ($changeset->getChangeType() == DifferentialChangeType::TYPE_DELETE &&
        $old == array('unix:filemode' => '100644')) {
      return false;
     }

    return true;
  }

  public function render(
    $range_start  = null,
    $range_len    = null,
    $mask_force   = array()) {

    // "Top level" renders are initial requests for the whole file, versus
    // requests for a specific range generated by clicking "show more". We
    // generate property changes and "shield" UI elements only for toplevel
    // requests.
    $this->isTopLevel = (($range_start === null) && ($range_len === null));
    $this->highlightEngine = PhabricatorSyntaxHighlighter::newEngine();
    $this->tryCacheStuff();
    $render_pch = $this->shouldRenderPropertyChangeHeader($this->changeset);

    $rows = max(
      count($this->old),
      count($this->new));

    $renderer = $this->getRenderer()
      ->setChangeset($this->changeset)
      ->setRenderPropertyChangeHeader($render_pch)
      ->setOldRender($this->oldRender)
      ->setNewRender($this->newRender)
      ->setHunkStartLines($this->hunkStartLines)
      ->setOldChangesetID($this->leftSideChangesetID)
      ->setNewChangesetID($this->rightSideChangesetID)
      ->setOldAttachesToNewFile($this->leftSideAttachesToNewFile)
      ->setNewAttachesToNewFile($this->rightSideAttachesToNewFile)
      ->setCodeCoverage($this->getCoverage())
      ->setRenderingReference($this->getRenderingReference())
      ->setMarkupEngine($this->markupEngine)
      ->setHandles($this->handles)
      ->setOldLines($this->old)
      ->setNewLines($this->new);

    if ($this->user) {
      $renderer->setUser($this->user);
    }

    $shield = null;
    if ($this->isTopLevel && !$this->comments) {
      if ($this->isGenerated()) {
        $shield = $renderer->renderShield(
          pht(
            'This file contains generated code, which does not normally '.
            'need to be reviewed.'));
      } else if ($this->isUnchanged()) {
        $type = 'text';
        if (!$rows) {
          // NOTE: Normally, diffs which don't change files do not include
          // file content (for example, if you "chmod +x" a file and then
          // run "git show", the file content is not available). Similarly,
          // if you move a file from A to B without changing it, diffs normally
          // do not show the file content. In some cases `arc` is able to
          // synthetically generate content for these diffs, but for raw diffs
          // we'll never have it so we need to be prepared to not render a link.
          $type = 'none';
        }
        $shield = $renderer->renderShield(
          pht('The contents of this file were not changed.'),
          $type);
      } else if ($this->isWhitespaceOnly()) {
        $shield = $renderer->renderShield(
          pht('This file was changed only by adding or removing whitespace.'),
          'whitespace');
      } else if ($this->isDeleted()) {
        $shield = $renderer->renderShield(
          pht('This file was completely deleted.'));
      } else if ($this->changeset->getAffectedLineCount() > 2500) {
        $lines = number_format($this->changeset->getAffectedLineCount());
        $shield = $renderer->renderShield(
          pht(
            'This file has a very large number of changes (%s lines).',
            $lines));
      }
    }

    if ($shield) {
      return $renderer->renderChangesetTable($shield);
    }

    $old_comments = array();
    $new_comments = array();
    $old_mask = array();
    $new_mask = array();
    $feedback_mask = array();

    if ($this->comments) {
      foreach ($this->comments as $comment) {
        $start = max($comment->getLineNumber() - self::LINES_CONTEXT, 0);
        $end = $comment->getLineNumber() +
          $comment->getLineLength() +
          self::LINES_CONTEXT;
        $new_side = $this->isCommentOnRightSideWhenDisplayed($comment);
        for ($ii = $start; $ii <= $end; $ii++) {
          if ($new_side) {
            $new_mask[$ii] = true;
          } else {
            $old_mask[$ii] = true;
          }
        }
      }

      foreach ($this->old as $ii => $old) {
        if (isset($old['line']) && isset($old_mask[$old['line']])) {
          $feedback_mask[$ii] = true;
        }
      }

      foreach ($this->new as $ii => $new) {
        if (isset($new['line']) && isset($new_mask[$new['line']])) {
          $feedback_mask[$ii] = true;
        }
      }
      $this->comments = msort($this->comments, 'getID');
      foreach ($this->comments as $comment) {
        $final = $comment->getLineNumber() +
          $comment->getLineLength();
        $final = max(1, $final);
        if ($this->isCommentOnRightSideWhenDisplayed($comment)) {
          $new_comments[$final][] = $comment;
        } else {
          $old_comments[$final][] = $comment;
        }
      }
    }
    $renderer
      ->setOldComments($old_comments)
      ->setNewComments($new_comments);

    switch ($this->changeset->getFileType()) {
      case DifferentialChangeType::FILE_IMAGE:
        $old = null;
        $new = null;
        // TODO: Improve the architectural issue as discussed in D955
        // https://secure.phabricator.com/D955
        $reference = $this->getRenderingReference();
        $parts = explode('/', $reference);
        if (count($parts) == 2) {
          list($id, $vs) = $parts;
        } else {
          $id = $parts[0];
          $vs = 0;
        }
        $id = (int)$id;
        $vs = (int)$vs;

        if (!$vs) {
          $metadata = $this->changeset->getMetadata();
          $data = idx($metadata, 'attachment-data');

          $old_phid = idx($metadata, 'old:binary-phid');
          $new_phid = idx($metadata, 'new:binary-phid');
        } else {
          $vs_changeset = id(new DifferentialChangeset())->load($vs);
          $vs_metadata = $vs_changeset->getMetadata();
          $old_phid = idx($vs_metadata, 'new:binary-phid');

          $changeset = id(new DifferentialChangeset())->load($id);
          $metadata = $changeset->getMetadata();
          $new_phid = idx($metadata, 'new:binary-phid');
        }

        if ($old_phid || $new_phid) {
          // grab the files, (micro) optimization for 1 query not 2
          $file_phids = array();
          if ($old_phid) {
            $file_phids[] = $old_phid;
          }
          if ($new_phid) {
            $file_phids[] = $new_phid;
          }

          $files = id(new PhabricatorFile())->loadAllWhere(
            'phid IN (%Ls)',
            $file_phids);
          foreach ($files as $file) {
            if (empty($file)) {
              continue;
            }
            if ($file->getPHID() == $old_phid) {
              $old = $file;
            } else if ($file->getPHID() == $new_phid) {
              $new = $file;
            }
          }
        }

        return $renderer->renderFileChange($old, $new, $id, $vs);
      case DifferentialChangeType::FILE_DIRECTORY:
      case DifferentialChangeType::FILE_BINARY:
        $output = $renderer->renderChangesetTable(null);
        return $output;
    }

    if ($this->originalLeft && $this->originalRight) {
      list($highlight_old, $highlight_new) = $this->diffOriginals();
      $highlight_old = array_flip($highlight_old);
      $highlight_new = array_flip($highlight_new);
      $renderer
        ->setHighlightOld($highlight_old)
        ->setHighlightNew($highlight_new);
    }
    $renderer
      ->setOriginalOld($this->originalLeft)
      ->setOriginalNew($this->originalRight);

    if ($range_start === null) {
      $range_start = 0;
    }
    if ($range_len === null) {
      $range_len = $rows;
    }
    $range_len = min($range_len, $rows - $range_start);

    list($gaps, $mask, $depths) = $this->calculateGapsMaskAndDepths(
      $mask_force,
      $feedback_mask,
      $range_start,
      $range_len);

    $renderer
      ->setGaps($gaps)
      ->setMask($mask)
      ->setDepths($depths);

    $html = $renderer->renderTextChange(
      $range_start,
      $range_len,
      $rows);

    return $renderer->renderChangesetTable($html);
  }

  /**
   * This function calculates a lot of stuff we need to know to display
   * the diff:
   *
   * Gaps - compute gaps in the visible display diff, where we will render
   * "Show more context" spacers. If a gap is smaller than the context size,
   * we just display it. Otherwise, we record it into $gaps and will render a
   * "show more context" element instead of diff text below. A given $gap
   * is a tuple of $gap_line_number_start and $gap_length.
   *
   * Mask - compute the actual lines that need to be shown (because they
   * are near changes lines, near inline comments, or the request has
   * explicitly asked for them, i.e. resulting from the user clicking
   * "show more"). The $mask returned is a sparesely populated dictionary
   * of $visible_line_number => true.
   *
   * Depths - compute how indented any given line is. The $depths returned
   * is a sparesely populated dictionary of $visible_line_number => $depth.
   *
   * This function also has the side effect of modifying member variable
   * new such that tabs are normalized to spaces for each line of the diff.
   *
   * @return array($gaps, $mask, $depths)
   */
  private function calculateGapsMaskAndDepths($mask_force,
                                              $feedback_mask,
                                              $range_start,
                                              $range_len) {

    // Calculate gaps and mask first
    $gaps = array();
    $gap_start = 0;
    $in_gap = false;
    $base_mask = $this->visible + $mask_force + $feedback_mask;
    $base_mask[$range_start + $range_len] = true;
    for ($ii = $range_start; $ii <= $range_start + $range_len; $ii++) {
      if (isset($base_mask[$ii])) {
        if ($in_gap) {
          $gap_length = $ii - $gap_start;
          if ($gap_length <= self::LINES_CONTEXT) {
            for ($jj = $gap_start; $jj <= $gap_start + $gap_length; $jj++) {
              $base_mask[$jj] = true;
            }
          } else {
            $gaps[] = array($gap_start, $gap_length);
          }
          $in_gap = false;
        }
      } else {
        if (!$in_gap) {
          $gap_start = $ii;
          $in_gap = true;
        }
      }
    }
    $gaps = array_reverse($gaps);
    $mask = $base_mask;

    // Time to calculate depth.
    // We need to go backwards to properly indent whitespace in this code:
    //
    //   0: class C {
    //   1:
    //   1:   function f() {
    //   2:
    //   2:     return;
    //   3:
    //   3:   }
    //   4:
    //   4: }
    //
    $depths = array();
    $last_depth = 0;
    $range_end = $range_start + $range_len;
    if (!isset($this->new[$range_end])) {
      $range_end--;
    }
    for ($ii = $range_end; $ii >= $range_start; $ii--) {
      // We need to expand tabs to process mixed indenting and to round
      // correctly later.
      $line = str_replace("\t", "  ", $this->new[$ii]['text']);
      $trimmed = ltrim($line);
      if ($trimmed != '') {
        // We round down to flatten "/**" and " *".
        $last_depth = floor((strlen($line) - strlen($trimmed)) / 2);
      }
      $depths[$ii] = $last_depth;
    }

    return array($gaps, $mask, $depths);
  }

  /**
   * Determine if an inline comment will appear on the rendered diff,
   * taking into consideration which halves of which changesets will actually
   * be shown.
   *
   * @param PhabricatorInlineCommentInterface Comment to test for visibility.
   * @return bool True if the comment is visible on the rendered diff.
   */
  private function isCommentVisibleOnRenderedDiff(
    PhabricatorInlineCommentInterface $comment) {

      $changeset_id = $comment->getChangesetID();
      $is_new = $comment->getIsNewFile();

      if ($changeset_id == $this->rightSideChangesetID &&
        $is_new == $this->rightSideAttachesToNewFile) {
          return true;
        }

      if ($changeset_id == $this->leftSideChangesetID &&
        $is_new == $this->leftSideAttachesToNewFile) {
          return true;
        }

    return false;
  }


  /**
   * Determine if a comment will appear on the right side of the display diff.
   * Note that the comment must appear somewhere on the rendered changeset, as
   * per isCommentVisibleOnRenderedDiff().
   *
   * @param PhabricatorInlineCommentInterface Comment to test for display
   *              location.
   * @return bool True for right, false for left.
   */
  private function isCommentOnRightSideWhenDisplayed(
    PhabricatorInlineCommentInterface $comment) {

    if (!$this->isCommentVisibleOnRenderedDiff($comment)) {
      throw new Exception("Comment is not visible on changeset!");
    }

    $changeset_id = $comment->getChangesetID();
    $is_new = $comment->getIsNewFile();

    if ($changeset_id == $this->rightSideChangesetID &&
        $is_new == $this->rightSideAttachesToNewFile) {
      return true;
    }

    return false;
  }

  /**
   * Parse the 'range' specification that this class and the client-side JS
   * emit to indicate that a user clicked "Show more..." on a diff. Generally,
   * use is something like this:
   *
   *   $spec = $request->getStr('range');
   *   $parsed = DifferentialChangesetParser::parseRangeSpecification($spec);
   *   list($start, $end, $mask) = $parsed;
   *   $parser->render($start, $end, $mask);
   *
   * @param string Range specification, indicating the range of the diff that
   *               should be rendered.
   * @return tuple List of <start, end, mask> suitable for passing to
   *               @{method:render}.
   */
  public static function parseRangeSpecification($spec) {
    $range_s = null;
    $range_e = null;
    $mask = array();

    if ($spec) {
      $match = null;
      if (preg_match('@^(\d+)-(\d+)(?:/(\d+)-(\d+))?$@', $spec, $match)) {
        $range_s = (int)$match[1];
        $range_e = (int)$match[2];
        if (count($match) > 3) {
          $start = (int)$match[3];
          $len = (int)$match[4];
          for ($ii = $start; $ii < $start + $len; $ii++) {
            $mask[$ii] = true;
          }
        }
      }
    }

    return array($range_s, $range_e, $mask);
  }

  /**
   * Render "modified coverage" information; test coverage on modified lines.
   * This synthesizes diff information with unit test information into a useful
   * indicator of how well tested a change is.
   */
  public function renderModifiedCoverage() {
    $na = phutil_tag('em', array(), '-');

    $coverage = $this->getCoverage();
    if (!$coverage) {
      return $na;
    }

    $covered = 0;
    $not_covered = 0;

    foreach ($this->new as $k => $new) {
      if (!$new['line']) {
        continue;
      }

      if (!$new['type']) {
        continue;
      }

      if (empty($coverage[$new['line'] - 1])) {
        continue;
      }

      switch ($coverage[$new['line'] - 1]) {
        case 'C':
          $covered++;
          break;
        case 'U':
          $not_covered++;
          break;
      }
    }

    if (!$covered && !$not_covered) {
      return $na;
    }

    return sprintf('%d%%', 100 * ($covered / ($covered + $not_covered)));
  }

  public function detectCopiedCode(
    array $changesets,
    $min_width = 30,
    $min_lines = 3) {

    assert_instances_of($changesets, 'DifferentialChangeset');

    $map = array();
    $files = array();
    $types = array();
    foreach ($changesets as $changeset) {
      $file = $changeset->getFilename();
      foreach ($changeset->getHunks() as $hunk) {
        $line = $hunk->getOldOffset();
        foreach (explode("\n", $hunk->getChanges()) as $code) {
          $type = (isset($code[0]) ? $code[0] : '');
          if ($type == '-' || $type == ' ') {
            $code = trim(substr($code, 1));
            $files[$file][$line] = $code;
            $types[$file][$line] = $type;
            if (strlen($code) >= $min_width) {
              $map[$code][] = array($file, $line);
            }
            $line++;
          }
        }
      }
    }

    foreach ($changesets as $changeset) {
      $copies = array();
      foreach ($changeset->getHunks() as $hunk) {
        $added = array_map('trim', $hunk->getAddedLines());
        for (reset($added); list($line, $code) = each($added); ) {
          if (isset($map[$code])) { // We found a long matching line.
            $best_length = 0;
            foreach ($map[$code] as $val) { // Explore all candidates.
              list($file, $orig_line) = $val;
              $length = 1;
              // Search also backwards for short lines.
              foreach (array(-1, 1) as $direction) {
                $offset = $direction;
                while (!isset($copies[$line + $offset]) &&
                    isset($added[$line + $offset]) &&
                    idx($files[$file], $orig_line + $offset) ===
                      $added[$line + $offset]) {
                  $length++;
                  $offset += $direction;
                }
              }
              if ($length > $best_length ||
                  ($length == $best_length && // Prefer moves.
                   idx($types[$file], $orig_line) == '-')) {
                $best_length = $length;
                // ($offset - 1) contains number of forward matching lines.
                $best_offset = $offset - 1;
                $best_file = $file;
                $best_line = $orig_line;
              }
            }
            $file = ($best_file == $changeset->getFilename() ? '' : $best_file);
            for ($i = $best_length; $i--; ) {
              $type = idx($types[$best_file], $best_line + $best_offset - $i);
              $copies[$line + $best_offset - $i] = ($best_length < $min_lines
                ? array() // Ignore short blocks.
                : array($file, $best_line + $best_offset - $i, $type));
            }
            for ($i = 0; $i < $best_offset; $i++) {
              next($added);
            }
          }
        }
      }
      $copies = array_filter($copies);
      if ($copies) {
        $metadata = $changeset->getMetadata();
        $metadata['copy:lines'] = $copies;
        $changeset->setMetadata($metadata);
      }
    }
    return $changesets;
  }

}
