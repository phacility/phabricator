<?php

final class DifferentialChangesetParser {

  const HIGHLIGHT_BYTE_LIMIT = 262144;

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
  private $characterEncoding;
  private $highlightAs;
  private $highlightingDisabled;
  private $showEditAndReplyLinks = true;
  private $canMarkDone;
  private $objectOwnerPHID;

  private $rangeStart;
  private $rangeEnd;
  private $mask;

  public function setRange($start, $end) {
    $this->rangeStart = $start;
    $this->rangeEnd = $end;
    return $this;
  }

  public function setMask(array $mask) {
    $this->mask = $mask;
    return $this;
  }

  public function renderChangeset() {
    return $this->render($this->rangeStart, $this->rangeEnd, $this->mask);
  }

  public function setShowEditAndReplyLinks($bool) {
    $this->showEditAndReplyLinks = $bool;
    return $this;
  }

  public function getShowEditAndReplyLinks() {
    return $this->showEditAndReplyLinks;
  }

  public function setHighlightAs($highlight_as) {
    $this->highlightAs = $highlight_as;
    return $this;
  }

  public function getHighlightAs() {
    return $this->highlightAs;
  }

  public function setCharacterEncoding($character_encoding) {
    $this->characterEncoding = $character_encoding;
    return $this;
  }

  public function getCharacterEncoding() {
    return $this->characterEncoding;
  }

  public function setRenderer(DifferentialChangesetRenderer $renderer) {
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

  public function setCanMarkDone($can_mark_done) {
    $this->canMarkDone = $can_mark_done;
    return $this;
  }

  public function getCanMarkDone() {
    return $this->canMarkDone;
  }

  public function setObjectOwnerPHID($phid) {
    $this->objectOwnerPHID = $phid;
    return $this;
  }

  public function getObjectOwnerPHID() {
    return $this->objectOwnerPHID;
  }

  public static function getDefaultRendererForViewer(PhabricatorUser $viewer) {
    $prefs = $viewer->loadPreferences();
    $pref_unified = PhabricatorUserPreferences::PREFERENCE_DIFF_UNIFIED;
    if ($prefs->getPreference($pref_unified) == 'unified') {
      return '1up';
    }
    return null;
  }

  public function readParametersFromRequest(AphrontRequest $request) {
    $this->setWhitespaceMode($request->getStr('whitespace'));
    $this->setCharacterEncoding($request->getStr('encoding'));
    $this->setHighlightAs($request->getStr('highlight'));

    $renderer = null;

    // If the viewer prefers unified diffs, always set the renderer to unified.
    // Otherwise, we leave it unspecified and the client will choose a
    // renderer based on the screen size.

    if ($request->getStr('renderer')) {
      $renderer = $request->getStr('renderer');
    } else {
      $renderer = self::getDefaultRendererForViewer($request->getViewer());
    }

    switch ($renderer) {
      case '1up':
        $this->setRenderer(new DifferentialChangesetOneUpRenderer());
        break;
      default:
        $this->setRenderer(new DifferentialChangesetTwoUpRenderer());
        break;
    }

    return $this;
  }

  const CACHE_VERSION = 11;
  const CACHE_MAX_SIZE = 8e6;

  const ATTR_GENERATED  = 'attr:generated';
  const ATTR_DELETED    = 'attr:deleted';
  const ATTR_UNCHANGED  = 'attr:unchanged';
  const ATTR_WHITELINES = 'attr:white';
  const ATTR_MOVEAWAY   = 'attr:moveaway';

  const LINES_CONTEXT = 8;

  const WHITESPACE_SHOW_ALL         = 'show-all';
  const WHITESPACE_IGNORE_TRAILING  = 'ignore-trailing';
  const WHITESPACE_IGNORE_MOST      = 'ignore-most';
  const WHITESPACE_IGNORE_ALL       = 'ignore-all';

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

  public function getUser() {
    return $this->user;
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
      'highlightingDisabled',
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

    $changeset = new DifferentialChangeset();
    $conn_w = $changeset->establishConnection('w');

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      try {
        queryfx(
          $conn_w,
          'INSERT INTO %T (id, cache, dateCreated) VALUES (%d, %B, %d)
            ON DUPLICATE KEY UPDATE cache = VALUES(cache)',
          DifferentialChangeset::TABLE_CACHE,
          $render_cache_key,
          $cache,
          time());
      } catch (AphrontQueryException $ex) {
        // Ignore these exceptions. A common cause is that the cache is
        // larger than 'max_allowed_packet', in which case we're better off
        // not writing it.

        // TODO: It would be nice to tailor this more narrowly.
      }
    unset($unguarded);
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

  public function isMoveAway() {
    return idx($this->specialAttributes, self::ATTR_MOVEAWAY, false);
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
    $language = $this->highlightAs;

    if (!$language) {
      $language = $this->highlightEngine->getLanguageFromFilename(
        $this->filename);

      if (($language != 'txt') &&
          (strlen($corpus) > self::HIGHLIGHT_BYTE_LIMIT)) {
        $this->highlightingDisabled = true;
        $language = 'txt';
      }
    }

    return $this->highlightEngine->getHighlightFuture(
      $language,
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
      case self::WHITESPACE_IGNORE_ALL:
        break;
      default:
        $whitespace_mode = self::WHITESPACE_IGNORE_MOST;
        break;
    }

    $skip_cache = ($whitespace_mode != self::WHITESPACE_IGNORE_MOST);
    if ($this->disableCache) {
      $skip_cache = true;
    }

    if ($this->characterEncoding) {
      $skip_cache = true;
    }

    if ($this->highlightAs) {
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

    $ignore_all = (($whitespace_mode == self::WHITESPACE_IGNORE_MOST) ||
                  ($whitespace_mode == self::WHITESPACE_IGNORE_ALL));

    $force_ignore = ($whitespace_mode == self::WHITESPACE_IGNORE_ALL);

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

    $moveaway = false;
    $changetype = $this->changeset->getChangeType();
    if ($changetype == DifferentialChangeType::TYPE_MOVE_AWAY) {
      $moveaway = true;
    }

    $this->setSpecialAttributes(array(
      self::ATTR_UNCHANGED  => $unchanged,
      self::ATTR_DELETED    => $hunk_parser->getIsDeleted(),
      self::ATTR_WHITELINES => !$hunk_parser->getHasTextChanges(),
      self::ATTR_MOVEAWAY   => $moveaway,
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
    foreach (new FutureIterator($futures) as $key => $future) {
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

    $encoding = null;
    if ($this->characterEncoding) {
      // We are forcing this changeset to be interpreted with a specific
      // character encoding, so force all the hunks into that encoding and
      // propagate it to the renderer.
      $encoding = $this->characterEncoding;
      foreach ($this->changeset->getHunks() as $hunk) {
        $hunk->forceEncoding($this->characterEncoding);
      }
    } else {
      // We're just using the default, so tell the renderer what that is
      // (by reading the encoding from the first hunk).
      foreach ($this->changeset->getHunks() as $hunk) {
        $encoding = $hunk->getDataEncoding();
        break;
      }
    }

    $this->tryCacheStuff();
    $render_pch = $this->shouldRenderPropertyChangeHeader($this->changeset);

    $rows = max(
      count($this->old),
      count($this->new));

    $renderer = $this->getRenderer()
      ->setUser($this->getUser())
      ->setChangeset($this->changeset)
      ->setRenderPropertyChangeHeader($render_pch)
      ->setIsTopLevel($this->isTopLevel)
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
      ->setNewLines($this->new)
      ->setOriginalCharacterEncoding($encoding)
      ->setShowEditAndReplyLinks($this->getShowEditAndReplyLinks())
      ->setCanMarkDone($this->getCanMarkDone())
      ->setObjectOwnerPHID($this->getObjectOwnerPHID())
      ->setHighlightingDisabled($this->highlightingDisabled);

    $shield = null;
    if ($this->isTopLevel && !$this->comments) {
      if ($this->isGenerated()) {
        $shield = $renderer->renderShield(
          pht(
            'This file contains generated code, which does not normally '.
            'need to be reviewed.'));
      } else if ($this->isMoveAway()) {
        // We put an empty shield on these files. Normally, they do not have
        // any diff content anyway. However, if they come through `arc`, they
        // may have content. We don't want to show it (it's not useful) and
        // we bailed out of fully processing it earlier anyway.

        // We could show a message like "this file was moved", but we show
        // that as a change header anyway, so it would be redundant. Instead,
        // just render an empty shield to skip rendering the diff body.
        $shield = '';
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

        $type_add = DifferentialChangeType::TYPE_ADD;
        if ($this->changeset->getChangeType() == $type_add) {
          // Although the generic message is sort of accurate in a technical
          // sense, this more-tailored message is less confusing.
          $shield = $renderer->renderShield(
            pht('This is an empty file.'),
            $type);
        } else {
          $shield = $renderer->renderShield(
            pht('The contents of this file were not changed.'),
            $type);
        }
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

    if ($shield !== null) {
      return $renderer->renderChangesetTable($shield);
    }

    // This request should render the "undershield" headers if it's a top-level
    // request which made it this far (indicating the changeset has no shield)
    // or it's a request with no mask information (indicating it's the request
    // that removes the rendering shield). Possibly, this second class of
    // request might need to be made more explicit.
    $is_undershield = (empty($mask_force) || $this->isTopLevel);
    $renderer->setIsUndershield($is_undershield);

    $old_comments = array();
    $new_comments = array();
    $old_mask = array();
    $new_mask = array();
    $feedback_mask = array();

    if ($this->comments) {
      // If there are any comments which appear in sections of the file which
      // we don't have, we're going to move them backwards to the closest
      // earlier line. Two cases where this may happen are:
      //
      //   - Porting ghost comments forward into a file which was mostly
      //     deleted.
      //   - Porting ghost comments forward from a full-context diff to a
      //     partial-context diff.

      list($old_backmap, $new_backmap) = $this->buildLineBackmaps();

      foreach ($this->comments as $comment) {
        $new_side = $this->isCommentOnRightSideWhenDisplayed($comment);

        $line = $comment->getLineNumber();
        if ($new_side) {
          $back_line = $new_backmap[$line];
        } else {
          $back_line = $old_backmap[$line];
        }

        if ($back_line != $line) {
          // TODO: This should probably be cleaner, but just be simple and
          // obvious for now.
          $ghost = $comment->getIsGhost();
          if ($ghost) {
            $moved = pht(
              'This comment originally appeared on line %s, but that line '.
              'does not exist in this version of the diff. It has been '.
              'moved backward to the nearest line.',
              new PhutilNumber($line));
            $ghost['reason'] = $ghost['reason']."\n\n".$moved;
            $comment->setIsGhost($ghost);
          }

          $comment->setLineNumber($back_line);
          $comment->setLineLength(0);

        }

        $start = max($comment->getLineNumber() - self::LINES_CONTEXT, 0);
        $end = $comment->getLineNumber() +
          $comment->getLineLength() +
          self::LINES_CONTEXT;
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
          $old_phid = null;
          $new_phid = null;

          // TODO: This is spooky, see D6851
          if ($vs_changeset) {
            $vs_metadata = $vs_changeset->getMetadata();
            $old_phid = idx($vs_metadata, 'new:binary-phid');
          }

          $changeset = id(new DifferentialChangeset())->load($id);
          if ($changeset) {
            $metadata = $changeset->getMetadata();
            $new_phid = idx($metadata, 'new:binary-phid');
          }
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

          $files = id(new PhabricatorFileQuery())
            ->setViewer($this->getUser())
            ->withPHIDs($file_phids)
            ->execute();
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

        $renderer->attachOldFile($old);
        $renderer->attachNewFile($new);

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
    //   1:
    //   1:   }
    //   0:
    //   0: }
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
      $line = str_replace("\t", '  ', $this->new[$ii]['text']);
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
      throw new Exception('Comment is not visible on changeset!');
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
        $lines = $hunk->getStructuredOldFile();
        foreach ($lines as $line => $info) {
          $type = $info['type'];
          if ($type == '\\') {
            continue;
          }
          $types[$file][$line] = $type;

          $text = $info['text'];
          $text = trim($text);
          $files[$file][$line] = $text;

          if (strlen($text) >= $min_width) {
            $map[$text][] = array($file, $line);
          }
        }
      }
    }

    foreach ($changesets as $changeset) {
      $copies = array();
      foreach ($changeset->getHunks() as $hunk) {
        $added = $hunk->getStructuredNewFile();
        $atype = array();

        foreach ($added as $line => $info) {
          $atype[$line] = $info['type'];
          $added[$line] = trim($info['text']);
        }

        $skip_lines = 0;
        foreach ($added as $line => $code) {
          if ($skip_lines) {
            // We're skipping lines that we already processed because we
            // extended a block above them downward to include them.
            $skip_lines--;
            continue;
          }

          if ($atype[$line] !== '+') {
            // This line hasn't been changed in the new file, so don't try
            // to figure out where it came from.
            continue;
          }

          if (empty($map[$code])) {
            // This line was too short to trigger copy/move detection.
            continue;
          }

          if (count($map[$code]) > 16) {
            // If there are a large number of identical lines in this diff,
            // don't try to figure out where this block came from: the analysis
            // is O(N^2), since we need to compare every line against every
            // other line. Even if we arrive at a result, it is unlikely to be
            // meaningful. See T5041.
            continue;
          }

          $best_length = 0;

          // Explore all candidates.
          foreach ($map[$code] as $val) {
            list($file, $orig_line) = $val;
            $length = 1;

            // Search backward and forward to find all of the adjacent lines
            // which match.
            foreach (array(-1, 1) as $direction) {
              $offset = $direction;
              while (true) {
                if (isset($copies[$line + $offset])) {
                  // If we run into a block above us which we've already
                  // attributed to a move or copy from elsewhere, stop
                  // looking.
                  break;
                }

                if (!isset($added[$line + $offset])) {
                  // If we've run off the beginning or end of the new file,
                  // stop looking.
                  break;
                }

                if (!isset($files[$file][$orig_line + $offset])) {
                  // If we've run off the beginning or end of the original
                  // file, we also stop looking.
                  break;
                }

                $old = $files[$file][$orig_line + $offset];
                $new = $added[$line + $offset];
                if ($old !== $new) {
                  // If the old line doesn't match the new line, stop
                  // looking.
                  break;
                }

                $length++;
                $offset += $direction;
              }
            }

            if ($length < $best_length) {
              // If we already know of a better source (more matching lines)
              // for this move/copy, stick with that one. We prefer long
              // copies/moves which match a lot of context over short ones.
              continue;
            }

            if ($length == $best_length) {
              if (idx($types[$file], $orig_line) != '-') {
                // If we already know of an equally good source (same number
                // of matching lines) and this isn't a move, stick with the
                // other one. We prefer moves over copies.
                continue;
              }
            }

            $best_length = $length;
            // ($offset - 1) contains number of forward matching lines.
            $best_offset = $offset - 1;
            $best_file = $file;
            $best_line = $orig_line;
          }

          $file = ($best_file == $changeset->getFilename() ? '' : $best_file);
          for ($i = $best_length; $i--; ) {
            $type = idx($types[$best_file], $best_line + $best_offset - $i);
            $copies[$line + $best_offset - $i] = ($best_length < $min_lines
              ? array() // Ignore short blocks.
              : array($file, $best_line + $best_offset - $i, $type));
          }

          $skip_lines = $best_offset;
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

  /**
   * Build maps from lines comments appear on to actual lines.
   */
  private function buildLineBackmaps() {
    $old_back = array();
    $new_back = array();
    foreach ($this->old as $ii => $old) {
      $old_back[$old['line']] = $old['line'];
    }
    foreach ($this->new as $ii => $new) {
      $new_back[$new['line']] = $new['line'];
    }

    $max_old_line = 0;
    $max_new_line = 0;
    foreach ($this->comments as $comment) {
      if ($this->isCommentOnRightSideWhenDisplayed($comment)) {
        $max_new_line = max($max_new_line, $comment->getLineNumber());
      } else {
        $max_old_line = max($max_old_line, $comment->getLineNumber());
      }
    }

    $cursor = 1;
    for ($ii = 1; $ii <= $max_old_line; $ii++) {
      if (empty($old_back[$ii])) {
        $old_back[$ii] = $cursor;
      } else {
        $cursor = $old_back[$ii];
      }
    }

    $cursor = 1;
    for ($ii = 1; $ii <= $max_new_line; $ii++) {
      if (empty($new_back[$ii])) {
        $new_back[$ii] = $cursor;
      } else {
        $cursor = $new_back[$ii];
      }
    }

    return array($old_back, $new_back);
  }

}
