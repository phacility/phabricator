<?php

final class DifferentialChangesetParser {

  protected $visible      = array();
  protected $new          = array();
  protected $old          = array();
  protected $intra        = array();
  protected $newRender    = null;
  protected $oldRender    = null;

  protected $filename     = null;
  protected $missingOld   = array();
  protected $missingNew   = array();

  protected $comments     = array();
  protected $specialAttributes = array();

  protected $changeset;
  protected $whitespaceMode = null;

  protected $subparser;

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

  const CACHE_VERSION = 8;
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

    // Put changes side by side.
    $olds = array();
    $news = array();
    foreach ($changeset->getHunks() as $hunk) {
      $n_old = $hunk->getOldOffset();
      $n_new = $hunk->getNewOffset();
      $changes = phutil_split_lines($hunk->getChanges());
      foreach ($changes as $line) {
        $diff_type = $line[0]; // Change type in diff of diffs.
        $orig_type = $line[1]; // Change type in the original diff.
        if ($diff_type == ' ') {
          // Use the same key for lines that are next to each other.
          $key = max(last_key($olds), last_key($news)) + 1;
          $olds[$key] = null;
          $news[$key] = null;
        } else if ($diff_type == '-') {
          $olds[] = array($n_old, $orig_type);
        } else if ($diff_type == '+') {
          $news[] = array($n_new, $orig_type);
        }
        if (($diff_type == '-' || $diff_type == ' ') && $orig_type != '-') {
          $n_old++;
        }
        if (($diff_type == '+' || $diff_type == ' ') && $orig_type != '-') {
          $n_new++;
        }
      }
    }

    $offsets_old = $this->originalLeft->computeOffsets();
    $offsets_new = $this->originalRight->computeOffsets();

    // Highlight lines that were added on each side or removed on the other
    // side.
    $highlight_old = array();
    $highlight_new = array();
    $last = max(last_key($olds), last_key($news));
    for ($i = 0; $i <= $last; $i++) {
      if (isset($olds[$i])) {
        list($n, $type) = $olds[$i];
        if ($type == '+' ||
            ($type == ' ' && isset($news[$i]) && $news[$i][1] != ' ')) {
          $highlight_old[] = $offsets_old[$n];
        }
      }
      if (isset($news[$i])) {
        list($n, $type) = $news[$i];
        if ($type == '+' ||
            ($type == ' ' && isset($olds[$i]) && $olds[$i][1] != ' ')) {
          $highlight_new[] = $offsets_new[$n];
        }
      }
    }

    return array($highlight_old, $highlight_new);
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

  public function parseHunk(DifferentialHunk $hunk) {
    $lines = $hunk->getChanges();
    $lines = phutil_split_lines($lines);

    $types = array();
    foreach ($lines as $line_index => $line) {
      if (isset($line[0])) {
        $char = $line[0];
        if ($char == ' ') {
          $types[$line_index] = null;
        } else {
          $types[$line_index] = $char;
        }
      } else {
        $types[$line_index] = null;
      }
    }

    $old_line = $hunk->getOldOffset();
    $new_line = $hunk->getNewOffset();
    $num_lines = count($lines);

    if ($old_line > 1) {
      $this->missingOld[$old_line] = true;
    } else if ($new_line > 1) {
      $this->missingNew[$new_line] = true;
    }

    for ($cursor = 0; $cursor < $num_lines; $cursor++) {
      $type = $types[$cursor];
      $data = array(
        'type'  => $type,
        'text'  => (string)substr($lines[$cursor], 1),
        'line'  => $new_line,
      );
      if ($type == '\\') {
        $type = $types[$cursor - 1];
        $data['text'] = ltrim($data['text']);
      }
      switch ($type) {
        case '+':
          $this->new[] = $data;
          ++$new_line;
          break;
        case '-':
          $data['line'] = $old_line;
          $this->old[] = $data;
          ++$old_line;
          break;
        default:
          $this->new[] = $data;
          $data['line'] = $old_line;
          $this->old[] = $data;
          ++$new_line;
          ++$old_line;
          break;
      }
    }
  }

  public function parseInlineComment(
    PhabricatorInlineCommentInterface $comment) {

    // Parse only comments which are actually visible.
    if ($this->isCommentVisibleOnRenderedDiff($comment)) {
      $this->comments[] = $comment;
    }
    return $this;
  }

  public function process() {

    $old = array();
    $new = array();

    $this->old = array_reverse($this->old);
    $this->new = array_reverse($this->new);

    $whitelines = false;
    $changed = false;

    $skip_intra = array();
    while (count($this->old) || count($this->new)) {
      $o_desc = array_pop($this->old);
      $n_desc = array_pop($this->new);

      if ($o_desc) {
        $o_type = $o_desc['type'];
      } else {
        $o_type = null;
      }

      if ($n_desc) {
        $n_type = $n_desc['type'];
      } else {
        $n_type = null;
      }

      if (($o_type != null) && ($n_type == null)) {
        $old[] = $o_desc;
        $new[] = null;
        if ($n_desc) {
          array_push($this->new, $n_desc);
        }
        $changed = true;
        continue;
      }

      if (($n_type != null) && ($o_type == null)) {
        $old[] = null;
        $new[] = $n_desc;
        if ($o_desc) {
          array_push($this->old, $o_desc);
        }
        $changed = true;
        continue;
      }

      if ($this->whitespaceMode != self::WHITESPACE_SHOW_ALL) {
        $similar = false;
        switch ($this->whitespaceMode) {
          case self::WHITESPACE_IGNORE_TRAILING:
            if (rtrim($o_desc['text']) == rtrim($n_desc['text'])) {
              if ($o_desc['type']) {
                // If we're converting this into an unchanged line because of
                // a trailing whitespace difference, mark it as a whitespace
                // change so we can show "This file was modified only by
                // adding or removing trailing whitespace." instead of
                // "This file was not modified.".
                $whitelines = true;
              }
              $similar = true;
            }
            break;
          default:
            // In this case, the lines are similar if there is no change type
            // (that is, just trust the diff algorithm).
            if (!$o_desc['type']) {
              $similar = true;
            }
            break;
        }
        if ($similar) {
          if ($o_desc['type'] == '\\') {
            // These are similar because they're "No newline at end of file"
            // comments.
          } else {
            $o_desc['type'] = null;
            $n_desc['type'] = null;
            $skip_intra[count($old)] = true;
          }
        } else {
          $changed = true;
        }
      } else {
        $changed = true;
      }

      $old[] = $o_desc;
      $new[] = $n_desc;
    }

    $this->old = $old;
    $this->new = $new;

    $unchanged = false;
    if ($this->subparser) {
      $unchanged = $this->subparser->isUnchanged();
      $whitelines = $this->subparser->isWhitespaceOnly();
    } else if (!$changed) {
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

    $this->specialAttributes = array(
      self::ATTR_UNCHANGED  => $unchanged,
      self::ATTR_DELETED    => array_filter($this->old) &&
                               !array_filter($this->new),
      self::ATTR_WHITELINES => $whitelines
    );

    if ($this->isSubparser) {
      // The rest of this function deals with formatting the diff for display;
      // we can exit early if we're a subparser and avoid doing extra work.
      return;
    }

    if ($this->subparser) {

      // Use this parser's side-by-side line information -- notably, the
      // change types -- but replace all the line text with the subparser's.
      // This lets us render whitespace-only changes without marking them as
      // different.

      $old = $this->old;
      $new = $this->new;
      $old_text = ipull($this->subparser->old, 'text', 'line');
      $new_text = ipull($this->subparser->new, 'text', 'line');

      foreach ($old as $k => $desc) {
        if (empty($desc)) {
          continue;
        }
        $old[$k]['text'] = idx($old_text, $desc['line']);
      }
      foreach ($new as $k => $desc) {
        if (empty($desc)) {
          continue;
        }
        $new[$k]['text'] = idx($new_text, $desc['line']);

        if ($this->whitespaceMode == self::WHITESPACE_IGNORE_FORCE) {
          // Under forced ignore mode, ignore even internal whitespace
          // changes.
          continue;
        }

        // If there's a corresponding "old" text and the line is marked as
        // unchanged, test if there are internal whitespace changes between
        // non-whitespace characters, e.g. spaces added to a string or spaces
        // added around operators. If we find internal spaces, mark the line
        // as changed.
        //
        // We only need to do this for "new" lines because any line that is
        // missing either "old" or "new" text certainly can not have internal
        // whitespace changes without also having non-whitespace changes,
        // because characters had to be either added or removed to create the
        // possibility of internal whitespace.
        if (isset($old[$k]['text']) && empty($new[$k]['type'])) {
          if (trim($old[$k]['text']) != trim($new[$k]['text'])) {
            // The strings aren't the same when trimmed, so there are internal
            // whitespace changes. Mark this line changed.
            $old[$k]['type'] = '-';
            $new[$k]['type'] = '+';

            // Re-mark this line for intraline diffing.
            unset($skip_intra[$k]);
          }
        }
      }

      $this->old = $old;
      $this->new = $new;
    }

    $min_length = min(count($this->old), count($this->new));
    for ($ii = 0; $ii < $min_length; $ii++) {
      if ($this->old[$ii] || $this->new[$ii]) {
        if (isset($this->old[$ii]['text'])) {
          $otext = $this->old[$ii]['text'];
        } else {
          $otext = '';
        }
        if (isset($this->new[$ii]['text'])) {
          $ntext = $this->new[$ii]['text'];
        } else {
          $ntext = '';
        }
        if ($otext != $ntext && empty($skip_intra[$ii])) {
          $this->intra[$ii] = ArcanistDiffUtils::generateIntralineDiff(
            $otext,
            $ntext);
        }
      }
    }

    $lines_context = self::LINES_CONTEXT;
    $max_length = max(count($this->old), count($this->new));
    $old = $this->old;
    $new = $this->new;
    $visible = false;
    $last = 0;
    for ($cursor = -$lines_context; $cursor < $max_length; $cursor++) {
      $offset = $cursor + $lines_context;
      if ((isset($old[$offset]) && $old[$offset]['type']) ||
          (isset($new[$offset]) && $new[$offset]['type'])) {
        $visible = true;
        $last = $offset;
      } else if ($cursor > $last + $lines_context) {
        $visible = false;
      }
      if ($visible && $cursor > 0) {
        $this->visible[$cursor] = 1;
      }
    }

    $old_corpus = array();
    foreach ($this->old as $o) {
      if ($o['type'] != '\\') {
        if ($o['text'] === null) {
          // There's no text on this side of the diff, but insert a placeholder
          // newline so the highlighted line numbers match up.
          $old_corpus[] = "\n";
        } else {
          $old_corpus[] = $o['text'];
        }
      }
    }
    $old_corpus_block = implode('', $old_corpus);

    $new_corpus = array();
    foreach ($this->new as $n) {
      if ($n['type'] != '\\') {
        if ($n['text'] === null) {
          $new_corpus[] = "\n";
        } else {
          $new_corpus[] = $n['text'];
        }
      }
    }
    $new_corpus_block = implode('', $new_corpus);

    $this->markGenerated($new_corpus_block);

    if ($this->isTopLevel && !$this->comments &&
        ($this->isGenerated() || $this->isUnchanged() || $this->isDeleted())) {
      return;
    }

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

  public function loadCache() {
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

    $data = json_decode($data['cache'], true);
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
      'missingOld',
      'missingNew',
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
    $cache = json_encode($cache);

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
      $config_key = 'differential.generated-paths';
      $generated_path_regexps = PhabricatorEnv::getEnvConfig($config_key);
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

  protected function applyIntraline(&$render, $intra, $corpus) {

    foreach ($render as $key => $text) {
      if (isset($intra[$key])) {
        $render[$key] = ArcanistDiffUtils::applyIntralineDiff(
          $text,
          $intra[$key]);
      }
    }
  }

  protected function getHighlightFuture($corpus) {
    if (preg_match('/\r(?!\n)/', $corpus)) {
      // TODO: Pygments converts "\r" newlines into "\n" newlines, so we can't
      // use it on files with "\r" newlines. If we have "\r" not followed by
      // "\n" in the file, skip highlighting.
      $result = phutil_escape_html($corpus);
      return new ImmediateFuture($result);
    }

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
    $this->whitespaceMode = $whitespace_mode;

    $changeset = $this->changeset;

    if ($changeset->getFileType() != DifferentialChangeType::FILE_TEXT &&
        $changeset->getFileType() != DifferentialChangeType::FILE_SYMLINK) {
      $this->markGenerated();

    } else {
      if ($skip_cache || !$this->loadCache()) {

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

        if ($ignore_all) {

          // Huge mess. Generate a "-bw" (ignore all whitespace changes) diff,
          // parse it out, and then play a shell game with the parsed format
          // in process() so we highlight only changed lines but render
          // whitespace differences. If we don't do this, we either fail to
          // render whitespace changes (which is incredibly confusing,
          // especially for python) or often produce a much larger set of
          // differences than necessary.

          $engine = new PhabricatorDifferenceEngine();
          $engine->setIgnoreWhitespace(true);
          $no_whitespace_changeset = $engine->generateChangesetFromFileContent(
            $old_file,
            $new_file);

          // subparser takes over the current non-whitespace-ignoring changeset
          $subparser = new DifferentialChangesetParser();
          $subparser->isSubparser = true;
          $subparser->setChangeset($changeset);
          foreach ($changeset->getHunks() as $hunk) {
            $subparser->parseHunk($hunk);
          }
          // We need to call process() so that the subparser's values for
          // metadata (like 'unchanged') is correct.
          $subparser->process();

          $this->subparser = $subparser;

          // While we aren't updating $this->changeset (since it has a bunch
          // of metadata we need to preserve, so that headers like "this file
          // was moved" render correctly), we're overwriting the local
          // $changeset so that the block below will choose the synthetic
          // hunks we've built instead of the original hunks.
          $changeset = $no_whitespace_changeset;
        }

        // This either uses the real hunks, or synthetic hunks we built above.
        foreach ($changeset->getHunks() as $hunk) {
          $this->parseHunk($hunk);
        }
        $this->process();
        if (!$skip_cache) {
          $this->saveCache();
        }
      }
    }
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

    $renderer = id(new DifferentialChangesetTwoUpRenderer())
      ->setChangeset($this->changeset)
      ->setRenderPropertyChangeHeader($render_pch)
      ->setLineCount($rows)
      ->setOldRender($this->oldRender)
      ->setNewRender($this->newRender)
      ->setMissingOldLines($this->missingOld)
      ->setMissingNewLines($this->missingNew)
      ->setOldChangesetID($this->leftSideChangesetID)
      ->setNewChangesetID($this->rightSideChangesetID)
      ->setOldAttachesToNewFile($this->leftSideAttachesToNewFile)
      ->setNewAttachesToNewFile($this->rightSideAttachesToNewFile)
      ->setCodeCoverage($this->getCoverage())
      ->setRenderingReference($this->getRenderingReference())
      ->setMarkupEngine($this->markupEngine)
      ->setHandles($this->handles);

    if ($this->user) {
      $renderer->setUser($this->user);
    }

    $shield = null;
    if ($this->isTopLevel && !$this->comments) {
      if ($this->isGenerated()) {
        $shield = $renderer->renderShield(
          pht(
            'This file contains generated code, which does not normally '.
            'need to be reviewed.'),
          true);
      } else if ($this->isUnchanged()) {
        if ($this->isWhitespaceOnly()) {
          $shield = $renderer->renderShield(
            pht(
              'This file was changed only by adding or removing trailing '.
              'whitespace.'),
            false);
        } else {
          $shield = $renderer->renderShield(
            pht("The contents of this file were not changed."),
            false);
        }
      } else if ($this->isDeleted()) {
        $shield = $renderer->renderShield(
          pht("This file was completely deleted."),
          true);
      } else if ($this->changeset->getAffectedLineCount() > 2500) {
        $lines = number_format($this->changeset->getAffectedLineCount());
        $shield = $renderer->renderShield(
          pht(
            'This file has a very large number of changes ({%s} lines).',
            $lines),
          true);
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
      $range_len
    );

    $renderer
      ->setOldLines($this->old)
      ->setNewLines($this->new)
      ->setGaps($gaps)
      ->setMask($mask)
      ->setDepths($depths);

    $html = $renderer->renderTextChange(
      $range_start,
      $range_len,
      $rows
    );

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

  public function renderForEmail() {
    $ret = '';

    $min = min(count($this->old), count($this->new));
    for ($i = 0; $i < $min; $i++) {
      $o = $this->old[$i];
      $n = $this->new[$i];

      if (!isset($this->visible[$i])) {
        continue;
      }

      if ($o['line'] && $n['line']) {
        // It is quite possible there are better ways to achieve this. For
        // example, "white-space: pre;" can do a better job, WERE IT NOT for
        // broken email clients like OWA which use newlines to do weird
        // wrapping. So dont give them newlines.
        if (isset($this->intra[$i])) {
          $ret .= sprintf(
            "<font color=\"red\">-&nbsp;%s</font><br/>",
            str_replace(" ", "&nbsp;", phutil_escape_html($o['text']))
          );
          $ret .= sprintf(
            "<font color=\"green\">+&nbsp;%s</font><br/>",
            str_replace(" ", "&nbsp;", phutil_escape_html($n['text']))
          );
        } else {
          $ret .= sprintf("&nbsp;&nbsp;%s<br/>",
            str_replace(" ", "&nbsp;", phutil_escape_html($n['text']))
          );
        }
      } else if ($o['line'] && !$n['line']) {
        $ret .= sprintf(
          "<font color=\"red\">-&nbsp;%s</font><br/>",
          str_replace(" ", "&nbsp;", phutil_escape_html($o['text']))
        );
      } else {
        $ret .= sprintf(
          "<font color=\"green\">+&nbsp;%s</font><br/>",
          str_replace(" ", "&nbsp;", phutil_escape_html($n['text']))
        );
      }
    }

    return $ret;
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
    $na = '<em>-</em>';

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

}
