<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DifferentialChangesetParser {

  protected $visible      = array();
  protected $new          = array();
  protected $old          = array();
  protected $intra        = array();
  protected $newRender    = null;
  protected $oldRender    = null;
  protected $parsedHunk   = false;

  protected $filename     = null;
  protected $missingOld   = array();
  protected $missingNew   = array();

  protected $comments     = array();
  protected $specialAttributes = array();

  protected $changeset;
  protected $whitespaceMode = null;

  protected $subparser;

  protected $renderCacheKey = null;

  private $handles;
  private $user;

  private $leftSideChangesetID;
  private $leftSideAttachesToNewFile;

  private $rightSideChangesetID;
  private $rightSideAttachesToNewFile;

  private $renderingReference;
  private $isSubparser;

  private $lineWidth = 80;
  private $isTopLevel;

  const CACHE_VERSION = 4;

  const ATTR_GENERATED  = 'attr:generated';
  const ATTR_DELETED    = 'attr:deleted';
  const ATTR_UNCHANGED  = 'attr:unchanged';
  const ATTR_WHITELINES = 'attr:white';

  const LINES_CONTEXT = 8;

  const WHITESPACE_SHOW_ALL         = 'show-all';
  const WHITESPACE_IGNORE_TRAILING  = 'ignore-trailing';
  const WHITESPACE_IGNORE_ALL       = 'ignore-all';

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

  /**
   * Set the character width at which lines will be wrapped. Defaults to 80.
   *
   * @param   int Hard-wrap line-width for diff display.
   * @return this
   */
  public function setLineWidth($width) {
    $this->lineWidth = $width;
    return $this;
  }

  private function getRenderCacheKey() {
    return $this->renderCacheKey;
  }

  public function setChangeset($changeset) {
    $this->changeset = $changeset;

    $this->setFilename($changeset->getFilename());
    $this->setLineWidth($changeset->getWordWrapWidth());

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

  public function getChangeset() {
    return $this->changeset;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine(PhutilMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function parseHunk(DifferentialHunk $hunk) {
    $this->parsedHunk = true;
    $lines = $hunk->getChanges();

    $lines = str_replace(
      array("\t", "\r\n", "\r"),
      array('  ', "\n",   "\n"),
      $lines);
    $lines = explode("\n", $lines);

    $types = array();
    foreach ($lines as $line_index => $line) {
      if (isset($line[0])) {
        $char = $line[0];
        if ($char == ' ') {
          $types[$line_index] = null;
        } else if ($char == '\\' && $line_index > 0) {
          $types[$line_index] = $types[$line_index - 1];
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

  public function getDisplayLine($offset, $length) {
    $start = 1;
    for ($ii = $offset; $ii > 0; $ii--) {
      if ($this->new[$ii] && $this->new[$ii]['line']) {
        $start = $this->new[$ii]['line'];
        break;
      }
    }
    $end = $start;
    for ($ii = $offset + $length; $ii < count($this->new); $ii++) {
      if ($this->new[$ii] && $this->new[$ii]['line']) {
        $end = $this->new[$ii]['line'];
        break;
      }
    }
    return "{$start},{$end}";
  }

  public function parseInlineComment(DifferentialInlineComment $comment) {
    // Parse only comments which are actually visible.
    if ($this->isCommentVisibleOnRenderedDiff($comment)) {
      $this->comments[] = $comment;
    }
    return $this;
  }

  public function process() {

    $old = array();
    $new = array();

    $n = 0;

    $this->old = array_reverse($this->old);
    $this->new = array_reverse($this->new);

    $whitelines = false;
    $changed = false;

    $skip_intra = array();
    while (count($this->old) || count($this->new)) {
      $o_desc = array_pop($this->old);
      $n_desc = array_pop($this->new);

      $oend = end($this->old);
      if ($oend) {
        $o_next = $oend['type'];
      } else {
        $o_next = null;
      }

      $nend = end($this->new);
      if ($nend) {
        $n_next = $nend['type'];
      } else {
        $n_next = null;
      }

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
          $o_desc['type'] = null;
          $n_desc['type'] = null;
          $skip_intra[count($old)] = true;
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

    // NOTE: Micro-optimize a couple of ipull()s here since it gives us a
    // 10% performance improvement for certain types of large diffs like
    // Phriction changes.

    $old_corpus = array();
    foreach ($this->old as $o) {
      $old_corpus[] = $o['text'];
    }
    $old_corpus_block = implode("\n", $old_corpus);

    $new_corpus = array();
    foreach ($this->new as $n) {
      $new_corpus[] = $n['text'];
    }
    $new_corpus_block = implode("\n", $new_corpus);

    $old_future = $this->getHighlightFuture($old_corpus_block);
    $new_future = $this->getHighlightFuture($new_corpus_block);
    $futures = array(
      'old' => $old_future,
      'new' => $new_future,
    );
    foreach (Futures($futures) as $key => $future) {
      try {
        switch ($key) {
          case 'old':
            $this->oldRender = $this->processHighlightedSource(
              $this->old,
              $future->resolve());
            break;
          case 'new':
            $this->newRender = $this->processHighlightedSource(
              $this->new,
              $future->resolve());
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

    $generated = (strpos($new_corpus_block, '@'.'generated') !== false);

    $this->specialAttributes[self::ATTR_GENERATED] = $generated;
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

  public function getLength() {
    return max(count($this->old), count($this->new));
  }

  protected function applyIntraline(&$render, $intra, $corpus) {
    foreach ($render as $key => $text) {
      if (isset($intra[$key])) {
        $render[$key] = ArcanistDiffUtils::applyIntralineDiff(
          $text,
          $intra[$key]);
      }
      if (isset($corpus[$key]) && strlen($corpus[$key]) > $this->lineWidth) {
        $render[$key] = $this->lineWrap($render[$key]);
      }
    }
  }

  /**
   * Hard-wrap a piece of UTF-8 text with embedded HTML tags and entities.
   *
   * @param   string An HTML string with tags and entities.
   * @return  string Hard-wrapped string.
   */
  protected function lineWrap($line) {
    $c = 0;
    $break_here = array();

    // Convert the UTF-8 string into a list of UTF-8 characters.
    $vector = phutil_utf8v($line);
    $len = count($vector);
    $byte_pos = 0;
    for ($ii = 0; $ii < $len; ++$ii) {
      // An ampersand indicates an HTML entity; consume the whole thing (until
      // ";") but treat it all as one character.
      if ($vector[$ii] == '&') {
        do {
          ++$ii;
        } while ($vector[$ii] != ';');
        ++$c;
      // An "<" indicates an HTML tag, consume the whole thing but don't treat
      // it as a character.
      } else if ($vector[$ii] == '<') {
        do {
          ++$ii;
        } while ($vector[$ii] != '>');
      } else {
        ++$c;
      }

      // Keep track of where we need to break the string later.
      if ($c == $this->lineWidth) {
        $break_here[$ii] = true;
        $c = 0;
      }
    }

    $result = array();
    foreach ($vector as $ii => $char) {
      $result[] = $char;
      if (isset($break_here[$ii])) {
        $result[] = "<span class=\"over-the-line\">\xE2\xAC\x85</span><br />";
      }
    }

    return implode('', $result);
  }

  protected function getHighlightFuture($corpus) {
    return $this->highlightEngine->getHighlightFuture(
      $this->highlightEngine->getLanguageFromFilename($this->filename),
      $corpus);
  }

  protected function processHighlightedSource($data, $result) {

    $result_lines = explode("\n", $result);
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
        break;
      default:
        $whitespace_mode = self::WHITESPACE_IGNORE_ALL;
        break;
    }

    $skip_cache = ($whitespace_mode != self::WHITESPACE_IGNORE_ALL);
    $this->whitespaceMode = $whitespace_mode;

    $changeset = $this->changeset;

    if ($changeset->getFileType() == DifferentialChangeType::FILE_TEXT ||
        $changeset->getFileType() == DifferentialChangeType::FILE_SYMLINK) {
      if ($skip_cache || !$this->loadCache()) {

        $ignore_all = ($this->whitespaceMode == self::WHITESPACE_IGNORE_ALL);

        if ($ignore_all && $changeset->getWhitespaceMatters()) {
          $ignore_all = false;
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

    $feedback_mask = array();

    switch ($this->changeset->getFileType()) {
      case DifferentialChangeType::FILE_IMAGE:
        $old = null;
        $cur = null;

        $metadata = $this->changeset->getMetadata();
        $data = idx($metadata, 'attachment-data');

        $old_phid = idx($metadata, 'old:binary-phid');
        $new_phid = idx($metadata, 'new:binary-phid');
        if ($old_phid || $new_phid) {
          if ($old_phid) {
            $old_uri = PhabricatorFileURI::getViewURIForPHID($old_phid);
            $old = phutil_render_tag(
              'img',
              array(
                'src' => $old_uri,
              ));
          }
          if ($new_phid) {
            $new_uri = PhabricatorFileURI::getViewURIForPHID($new_phid);
            $cur = phutil_render_tag(
              'img',
              array(
                'src' => $new_uri,
              ));
          }
        }

        $output = $this->renderChangesetTable(
          $this->changeset,
          '<tr>'.
            '<th></th>'.
            '<td class="differential-old-image">'.
              '<div class="differential-image-stage">'.
                $old.
              '</div>'.
            '</td>'.
            '<th></th>'.
            '<td class="differential-new-image">'.
              '<div class="differential-image-stage">'.
                $cur.
              '</div>'.
            '</td>'.
          '</tr>');

        return $output;
      case DifferentialChangeType::FILE_DIRECTORY:
      case DifferentialChangeType::FILE_BINARY:
        $output = $this->renderChangesetTable($this->changeset, null);
        return $output;
    }

    $shield = null;
    if ($this->isTopLevel && !$this->comments) {
      if ($this->isGenerated()) {
        $shield = $this->renderShield(
          "This file contains generated code, which does not normally need ".
          "to be reviewed.",
          true);
      } else if ($this->isUnchanged()) {
        if ($this->isWhitespaceOnly()) {
          $shield = $this->renderShield(
            "This file was changed only by adding or removing trailing ".
            "whitespace.",
            false);
        } else {
          $shield = $this->renderShield(
            "The contents of this file were not changed.",
            false);
        }
      } else if ($this->isDeleted()) {
        $shield = $this->renderShield(
          "This file was completely deleted.",
          true);
      } else if ($this->changeset->getAffectedLineCount() > 2500) {
        $lines = number_format($this->changeset->getAffectedLineCount());
        $shield = $this->renderShield(
          "This file has a very large number of changes ({$lines} lines).",
          true);
      }
    }

    if ($shield) {
      return $this->renderChangesetTable($this->changeset, $shield);
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
        $new = $this->isCommentOnRightSideWhenDisplayed($comment);
        for ($ii = $start; $ii <= $end; $ii++) {
          if ($new) {
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
        if ($this->isCommentOnRightSideWhenDisplayed($comment)) {
          $new_comments[$final][] = $comment;
        } else {
          $old_comments[$final][] = $comment;
        }
      }
    }

    $html = $this->renderTextChange(
      $range_start,
      $range_len,
      $mask_force,
      $feedback_mask,
      $old_comments,
      $new_comments);

    return $this->renderChangesetTable($this->changeset, $html);
  }

  /**
   * Determine if an inline comment will appear on the rendered diff,
   * taking into consideration which halves of which changesets will actually
   * be shown.
   *
   * @param DifferentialInlineComment Comment to test for visibility.
   * @return bool True if the comment is visible on the rendered diff.
   */
  private function isCommentVisibleOnRenderedDiff(
    DifferentialInlineComment $comment) {

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
   * @param DifferentialInlineComment Comment to test for display location.
   * @return bool True for right, false for left.
   */
  private function isCommentOnRightSideWhenDisplayed(
    DifferentialInlineComment $comment) {

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

  protected function renderShield($message, $more) {

    if ($more) {
      $end = $this->getLength();
      $reference = $this->renderingReference;
      $more =
        ' '.
        javelin_render_tag(
          'a',
          array(
            'mustcapture' => true,
            'sigil'       => 'show-more',
            'class'       => 'complete',
            'href'        => '#',
            'meta'        => array(
              'ref'         => $reference,
              'range'       => "0-{$end}",
            ),
          ),
          'Show File Contents');
    } else {
      $more = null;
    }

    return javelin_render_tag(
      'tr',
      array(
        'sigil' => 'context-target',
      ),
      '<td class="differential-shield" colspan="4">'.
        phutil_escape_html($message).
        $more.
      '</td>');
  }

  protected function renderTextChange(
    $range_start,
    $range_len,
    $mask_force,
    $feedback_mask,
    array $old_comments,
    array $new_comments) {

    $context_not_available = null;
    if ($this->missingOld || $this->missingNew) {
      $context_not_available = javelin_render_tag(
        'tr',
        array(
          'sigil' => 'context-target',
        ),
        '<td colspan="4" class="show-more">'.
            'Context not available.'.
        '</td>');
    }

    $html = array();

    $rows = max(
      count($this->old),
      count($this->new));

    if ($range_start === null) {
      $range_start = 0;
    }

    if ($range_len === null) {
      $range_len = $rows;
    }

    $range_len = min($range_len, $rows - $range_start);

    // Gaps - compute gaps in the visible display diff, where we will render
    // "Show more context" spacers. This builds an aggregate $mask of all the
    // lines we must show (because they are near changed lines, near inline
    // comments, or the request has explicitly asked for them, i.e. resulting
    // from the user clicking "show more") and then finds all the gaps between
    // visible lines. If a gap is smaller than the context size, we just
    // display it. Otherwise, we record it into $gaps and will render a
    // "show more context" element instead of diff text below.

    $gaps = array();
    $gap_start = 0;
    $in_gap = false;
    $mask = $this->visible + $mask_force + $feedback_mask;
    $mask[$range_start + $range_len] = true;
    for ($ii = $range_start; $ii <= $range_start + $range_len; $ii++) {
      if (isset($mask[$ii])) {
        if ($in_gap) {
          $gap_length = $ii - $gap_start;
          if ($gap_length <= self::LINES_CONTEXT) {
            for ($jj = $gap_start; $jj <= $gap_start + $gap_length; $jj++) {
              $mask[$jj] = true;
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

    $reference = $this->renderingReference;

    $left_id = $this->leftSideChangesetID;
    $right_id = $this->rightSideChangesetID;

    // "N" stands for 'new' and means the comment should attach to the new file
    // when stored, i.e. DifferentialInlineComment->setIsNewFile().
    // "O" stands for 'old' and means the comment should attach to the old file.

    $left_char = $this->leftSideAttachesToNewFile
      ? 'N'
      : 'O';
    $right_char = $this->rightSideAttachesToNewFile
      ? 'N'
      : 'O';

    for ($ii = $range_start; $ii < $range_start + $range_len; $ii++) {
      if (empty($mask[$ii])) {
        // If we aren't going to show this line, we've just entered a gap.
        // Pop information about the next gap off the $gaps stack and render
        // an appropriate "Show more context" element. This branch eventually
        // increments $ii by the entire size of the gap and then continues
        // the loop.
        $gap = array_pop($gaps);
        $top = $gap[0];
        $len = $gap[1];

        $end   = $top + $len - 20;

        $contents = array();

        if ($len > 40) {
          $is_first_block = false;
          if ($ii == 0) {
            $is_first_block = true;
          }

          $contents[] = javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'mustcapture' => true,
              'sigil'       => 'show-more',
              'meta'        => array(
                'ref'    => $reference,
                'range' => "{$top}-{$len}/{$top}-20",
              ),
            ),
            $is_first_block
              ? "Show First 20 Lines"
              : "\xE2\x96\xB2 Show 20 Lines");
        }

        $contents[] = javelin_render_tag(
          'a',
          array(
            'href' => '#',
            'mustcapture' => true,
            'sigil'       => 'show-more',
            'meta'        => array(
              'ref'    => $reference,
              'range' => "{$top}-{$len}/{$top}-{$len}",
            ),
          ),
          'Show All '.$len.' Lines');

        if ($len > 40) {
          $is_last_block = false;
          if ($ii + $len >= $rows) {
            $is_last_block = true;
          }

          $contents[] = javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'mustcapture' => true,
              'sigil'       => 'show-more',
              'meta'        => array(
                'ref'    => $reference,
                'range' => "{$top}-{$len}/{$end}-20",
              ),
            ),
            $is_last_block
              ? "Show Last 20 Lines"
              : "\xE2\x96\xBC Show 20 Lines");
        };

        $container = javelin_render_tag(
          'tr',
          array(
            'sigil' => 'context-target',
          ),
          '<td colspan="4" class="show-more">'.
            implode(' &bull; ', $contents).
          '</td>');

        $html[] = $container;

        $ii += ($len - 1);
        continue;
      }

      if (isset($this->old[$ii])) {
        $o_num  = $this->old[$ii]['line'];
        $o_text = isset($this->oldRender[$ii]) ? $this->oldRender[$ii] : null;
        $o_attr = null;
        if ($this->old[$ii]['type']) {
          if (empty($this->new[$ii])) {
            $o_attr = ' class="old old-full"';
          } else {
            $o_attr = ' class="old"';
          }
        }
      } else {
        $o_num  = null;
        $o_text = null;
        $o_attr = null;
      }

      if (isset($this->new[$ii])) {
        $n_num  = $this->new[$ii]['line'];
        $n_text = isset($this->newRender[$ii]) ? $this->newRender[$ii] : null;
        $n_attr = null;
        if ($this->new[$ii]['type']) {
          if (empty($this->old[$ii])) {
            $n_attr = ' class="new new-full"';
          } else {
            $n_attr = ' class="new"';
          }
        }
      } else {
        $n_num   = null;
        $n_text  = null;
        $n_attr  = null;
      }


      if (($o_num && !empty($this->missingOld[$o_num])) ||
          ($n_num && !empty($this->missingNew[$n_num]))) {
        $html[] = $context_not_available;
      }

      if ($o_num && $left_id) {
        $o_id = ' id="C'.$left_id.$left_char.'L'.$o_num.'"';
      } else {
        $o_id = null;
      }

      if ($n_num && $right_id) {
        $n_id = ' id="C'.$right_id.$right_char.'L'.$n_num.'"';
      } else {
        $n_id = null;
      }

      // NOTE: The Javascript is sensitive to whitespace changes in this
      // block!
      $html[] =
        '<tr>'.
          '<th'.$o_id.'>'.$o_num.'</th>'.
          '<td'.$o_attr.'>'.$o_text.'</td>'.
          '<th'.$n_id.'>'.$n_num.'</th>'.
          '<td'.$n_attr.'>'.$n_text.'</td>'.
        '</tr>';

      if ($context_not_available && ($ii == $rows - 1)) {
        $html[] = $context_not_available;
      }

      if ($o_num && isset($old_comments[$o_num])) {
        foreach ($old_comments[$o_num] as $comment) {
          $xhp = $this->renderInlineComment($comment);
          $html[] =
            '<tr class="inline"><th /><td>'.
              $xhp.
            '</td><th /><td /></tr>';
        }
      }
      if ($n_num && isset($new_comments[$n_num])) {
        foreach ($new_comments[$n_num] as $comment) {
          $xhp = $this->renderInlineComment($comment);
          $html[] =
            '<tr class="inline"><th /><td /><th /><td>'.
              $xhp.
            '</td></tr>';
        }
      }
    }

    return implode('', $html);
  }

  private function renderInlineComment(DifferentialInlineComment $comment) {

    $user = $this->user;
    $edit = $user &&
            ($comment->getAuthorPHID() == $user->getPHID()) &&
            (!$comment->getCommentID());

    $on_right = $this->isCommentOnRightSideWhenDisplayed($comment);

    return id(new DifferentialInlineCommentView())
      ->setInlineComment($comment)
      ->setOnRight($on_right)
      ->setHandles($this->handles)
      ->setMarkupEngine($this->markupEngine)
      ->setEditable($edit)
      ->render();
  }

  protected function renderPropertyChangeHeader($changeset) {
    if (!$this->isTopLevel) {
      // We render properties only at top level; otherwise we get multiple
      // copies of them when a user clicks "Show More".
      return null;
    }

    $old = $changeset->getOldProperties();
    $new = $changeset->getNewProperties();

    if ($old === $new) {
      return null;
    }

    if ($changeset->getChangeType() == DifferentialChangeType::TYPE_ADD &&
        $new == array('unix:filemode' => '100644')) {
      return null;
    }

    if ($changeset->getChangeType() == DifferentialChangeType::TYPE_DELETE &&
        $old == array('unix:filemode' => '100644')) {
      return null;
    }

    $keys = array_keys($old + $new);
    sort($keys);

    $rows = array();
    foreach ($keys as $key) {
      $oval = idx($old, $key);
      $nval = idx($new, $key);
      if ($oval !== $nval) {
        if ($oval === null) {
          $oval = '<em>null</em>';
        } else {
          $oval = phutil_escape_html($oval);
        }

        if ($nval === null) {
          $nval = '<em>null</em>';
        } else {
          $nval = phutil_escape_html($nval);
        }

        $rows[] =
          '<tr>'.
            '<th>'.phutil_escape_html($key).'</th>'.
            '<td class="oval">'.$oval.'</td>'.
            '<td class="nval">'.$nval.'</td>'.
          '</tr>';
      }
    }

    return
      '<table class="differential-property-table">'.
        '<tr class="property-table-header">'.
          '<th>Property Changes</th>'.
          '<td class="oval">Old Value</td>'.
          '<td class="nval">New Value</td>'.
        '</tr>'.
        implode('', $rows).
      '</table>';
  }

  protected function renderChangesetTable($changeset, $contents) {
    $props  = $this->renderPropertyChangeHeader($this->changeset);
    $table = null;
    if ($contents) {
      $table =
        '<table class="differential-diff remarkup-code PhabricatorMonospaced">'.
          $contents.
        '</table>';
    }

    if (!$table && !$props) {
      $notice = $this->renderChangeTypeHeader($this->changeset, true);
    } else {
      $notice = $this->renderChangeTypeHeader($this->changeset, false);
    }

    return implode(
      "\n",
      array(
        $notice,
        $props,
        $table,
      ));
  }

  protected function renderChangeTypeHeader($changeset, $force) {

    static $articles = array(
      DifferentialChangeType::FILE_IMAGE      => 'an',
    );

    static $files = array(
      DifferentialChangeType::FILE_TEXT       => 'file',
      DifferentialChangeType::FILE_IMAGE      => 'image',
      DifferentialChangeType::FILE_DIRECTORY  => 'directory',
      DifferentialChangeType::FILE_BINARY     => 'binary file',
      DifferentialChangeType::FILE_SYMLINK    => 'symlink',
    );

    static $changes = array(
      DifferentialChangeType::TYPE_ADD        => 'added',
      DifferentialChangeType::TYPE_CHANGE     => 'changed',
      DifferentialChangeType::TYPE_DELETE     => 'deleted',
      DifferentialChangeType::TYPE_MOVE_HERE  => 'moved from',
      DifferentialChangeType::TYPE_COPY_HERE  => 'copied from',
      DifferentialChangeType::TYPE_MOVE_AWAY  => 'moved to',
      DifferentialChangeType::TYPE_COPY_AWAY  => 'copied to',
      DifferentialChangeType::TYPE_MULTICOPY
        => 'deleted after being copied to',
    );

    $change = $changeset->getChangeType();
    $file = $changeset->getFileType();

    $message = null;
    if ($change == DifferentialChangeType::TYPE_CHANGE &&
        $file   == DifferentialChangeType::FILE_TEXT) {
      if ($force) {
        // We have to force something to render because there were no changes
        // of other kinds.
        $message = "This {$files[$file]} was not modified.";
      } else {
        // Default case of changes to a text file, no metadata.
        return null;
      }
    } else {
      $verb = idx($changes, $change, 'changed');
      switch ($change) {
        default:
          $message = "This {$files[$file]} was <strong>{$verb}</strong>.";
          break;
        case DifferentialChangeType::TYPE_MOVE_HERE:
        case DifferentialChangeType::TYPE_COPY_HERE:
          $message =
            "This {$files[$file]} was {$verb} ".
            "<strong>{$changeset->getOldFile()}</strong>.";
          break;
        case DifferentialChangeType::TYPE_MOVE_AWAY:
        case DifferentialChangeType::TYPE_COPY_AWAY:
        case DifferentialChangeType::TYPE_MULTICOPY:
          $paths = $changeset->getAwayPaths();
          if (count($paths) > 1) {
            $message =
              "This {$files[$file]} was {$verb}: ".
              "<strong>".implode(', ', $paths)."</strong>.";
          } else {
            $message =
              "This {$files[$file]} was {$verb} ".
              "<strong>".reset($paths)."</strong>.";
          }
          break;
        case DifferentialChangeType::TYPE_CHANGE:
          $message = "This is ".idx($articles, $file, 'a')." {$files[$file]}.";
          break;
      }
    }

    return
      '<div class="differential-meta-notice">'.
        $message.
      '</div>';
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

}
