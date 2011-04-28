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
  protected $filetype     = null;
  protected $changesetID  = null;
  protected $missingOld   = array();
  protected $missingNew   = array();

  protected $comments     = array();
  protected $specialAttributes = array();

  protected $changeset;
  protected $whitespaceMode = null;

  protected $subparser;
  protected $oldChangesetID = null;
  protected $noHighlight;

  private $handles;
  private $user;

  const CACHE_VERSION = 4;

  const ATTR_GENERATED  = 'attr:generated';
  const ATTR_DELETED    = 'attr:deleted';
  const ATTR_UNCHANGED  = 'attr:unchanged';
  const ATTR_WHITELINES = 'attr:white';

  const LINES_CONTEXT = 8;

  const WHITESPACE_SHOW_ALL         = 'show-all';
  const WHITESPACE_IGNORE_TRAILING  = 'ignore-trailing';
  const WHITESPACE_IGNORE_ALL       = 'ignore-all';

  public function setRightSideCommentMapping($id, $is_new) {

  }

  public function setLeftSideCommentMapping($id, $is_new) {

  }

  public function setChangeset($changeset) {
    $this->changeset = $changeset;

    $this->setFilename($changeset->getFilename());
    $this->setChangesetID($changeset->getID());

    return $this;
  }

  public function setWhitespaceMode($whitespace_mode) {
    $this->whitespaceMode = $whitespace_mode;
    return $this;
  }

  public function setOldChangesetID($old_changeset_id) {
    $this->oldChangesetID = $old_changeset_id;
    return $this;
  }

  public function setChangesetID($changeset_id) {
    $this->changesetID = $changeset_id;
    return $this;
  }

  public function getChangeset() {
    return $this->changeset;
  }

  public function getChangesetID() {
    return $this->changesetID;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    if (strpos($filename, '.', 1) !== false) {
      $parts = explode('.', $filename);
      $this->filetype = end($parts);
    }
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

    // Flatten UTF-8 into "\0". We don't support UTF-8 because the diffing
    // algorithms are byte-oriented (not character oriented) and everyone seems
    // to be in agreement that it's fairly reasonable not to allow UTF-8 in
    // source files. These bytes will later be replaced with a "?" glyph, but
    // in the meantime we replace them with "\0" since Pygments is happy to
    // deal with that.
    $lines = preg_replace('/[\x80-\xFF]/', "\0", $lines);

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
    $this->comments[] = $comment;
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
              $similar = true;
            }
            break;
        }
        if ($similar) {
          $o_desc['type'] = null;
          $n_desc['type'] = null;
          $skip_intra[count($old)] = true;
          $whitelines = true;
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

    $old_corpus = ipull($this->old, 'text');
    $old_corpus_block = implode("\n", $old_corpus);

    $new_corpus = ipull($this->new, 'text');
    $new_corpus_block = implode("\n", $new_corpus);

    if ($this->noHighlight) {
      $this->oldRender = explode("\n", phutil_escape_html($old_corpus_block));
      $this->newRender = explode("\n", phutil_escape_html($new_corpus_block));
    } else {
      $this->oldRender = $this->sourceHighlight($this->old, $old_corpus_block);
      $this->newRender = $this->sourceHighlight($this->new, $new_corpus_block);
    }

    $this->applyIntraline(
      $this->oldRender,
      ipull($this->intra, 0),
      $old_corpus);
    $this->applyIntraline(
      $this->newRender,
      ipull($this->intra, 1),
      $new_corpus);

    $this->tokenHighlight($this->oldRender);
    $this->tokenHighlight($this->newRender);

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

    $generated = (strpos($new_corpus_block, '@'.'generated') !== false);

    $this->specialAttributes = array(
      self::ATTR_GENERATED  => $generated,
      self::ATTR_UNCHANGED  => $unchanged,
      self::ATTR_DELETED    => array_filter($this->old) &&
                               !array_filter($this->new),
      self::ATTR_WHITELINES => $whitelines
    );
  }

  public function loadCache() {
    if (!$this->changesetID) {
      return false;
    }

    $data = null;

    $changeset = new DifferentialChangeset();
    $conn_r = $changeset->establishConnection('r');
    $data = queryfx_one(
      $conn_r,
      'SELECT * FROM %T WHERE id = %d',
      $changeset->getTableName().'_parse_cache',
      $this->changesetID);

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
    if (!$this->changesetID) {
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
      queryfx(
        $conn_w,
        'INSERT INTO %T (id, cache) VALUES (%d, %s)
          ON DUPLICATE KEY UPDATE cache = VALUES(cache)',
        $changeset->getTableName().'_parse_cache',
        $this->changesetID,
        $cache);
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
      if (isset($corpus[$key]) && strlen($corpus[$key]) > 80) {
        $render[$key] = $this->lineWrap($render[$key]);
      }
    }
  }

  protected function lineWrap($l) {
    $c = 0;
    $len = strlen($l);
    $ins = array();
    for ($ii = 0; $ii < $len; ++$ii) {
      if ($l[$ii] == '&') {
        do {
          ++$ii;
        } while ($l[$ii] != ';');
        ++$c;
      } else if ($l[$ii] == '<') {
        do {
          ++$ii;
        } while ($l[$ii] != '>');
      } else {
        ++$c;
      }
      if ($c == 80) {
        $ins[] = ($ii + 1);
        $c = 0;
      }
    }
    while (($pos = array_pop($ins))) {
      $l = substr_replace(
        $l,
        "<span class=\"over-the-line\">\xE2\xAC\x85</span><br />",
        $pos,
        0);
    }
    return $l;
  }


  protected function tokenHighlight(&$render) {
    foreach ($render as $key => $text) {
      $render[$key] = str_replace(
      "\0",
      '<span class="uu">'."\xEF\xBF\xBD".'</span>',
      $text);
    }
  }

  protected function sourceHighlight($data, $corpus) {
    $result = $this->highlightEngine->highlightSource(
      $this->filetype,
      $corpus);

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
        if ($this->whitespaceMode == self::WHITESPACE_IGNORE_ALL) {

          // Huge mess. Generate a "-bw" (ignore all whitespace changes) diff,
          // parse it out, and then play a shell game with the parsed format
          // in process() so we highlight only changed lines but render
          // whitespace differences. If we don't do this, we either fail to
          // render whitespace changes (which is incredibly confusing,
          // especially for python) or often produce a much larger set of
          // differences than necessary.

          $old_tmp = new TempFile();
          $new_tmp = new TempFile();
          Filesystem::writeFile($old_tmp, $changeset->makeOldFile());
          Filesystem::writeFile($new_tmp, $changeset->makeNewFile());
          list($err, $diff) = exec_manual(
            'diff -bw -U65535 %s %s',
            $old_tmp,
            $new_tmp);

          if (!strlen($diff)) {
            // If there's no diff text, that means the files are identical
            // except for whitespace changes. Build a synthetic, changeless
            // diff. TODO: this is incredibly hacky.
            $entire_file = explode("\n", $changeset->makeOldFile());
            foreach ($entire_file as $k => $line) {
              $entire_file[$k] = ' '.$line;
            }
            $len = count($entire_file);
            $entire_file = implode("\n", $entire_file);

            $diff = <<<EOSYNTHETIC
--- ignored 9999-99-99
+++ ignored 9999-99-99
@@ -{$len},{$len} +{$len},{$len} @@
{$entire_file}
EOSYNTHETIC;
          }

          // subparser takes over the current non-whitespace-ignoring changeset
          $this->subparser = new DifferentialChangesetParser();
          foreach ($changeset->getHunks() as $hunk) {
            $this->subparser->parseHunk($hunk);
          }

          // this parser takes new changeset; will use subparser's text later
          $changes = id(new ArcanistDiffParser())->parseDiff($diff);
          $diff = DifferentialDiff::newFromRawChanges($changes);
          $changesets = $diff->getChangesets();
          $changeset = reset($changesets);
          $this->setChangeset($changeset);
        }
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

    $this->highlightEngine = new PhutilDefaultSyntaxHighlighterEngine();

    $this->tryCacheStuff();

    $changeset_id = $this->changesetID;

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
    if ($range_start === null && $range_len === null) {
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
      } else if (preg_match('/\.sql3$/', $this->changeset->getFilename())) {
        $shield = $this->renderShield(
          ".sql3 files are hidden by default.",
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
        $new = $this->isCommentInNewFile($comment);
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
        if ($this->isCommentInNewFile($comment)) {
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

  private function isCommentInNewFile(DifferentialInlineComment $comment) {
    if ($this->oldChangesetID) {
      return ($comment->getChangesetID() != $this->oldChangesetID);
    } else {
      return $comment->getIsNewFile();
    }
  }

  protected function renderShield($message, $more) {

    if ($more) {
      $end = $this->getLength();
      $reference = $this->getChangeset()->getRenderingReference();
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
              'id'    => $reference,
              'range' => "0-{$end}",
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
      $context_not_available = $context_not_available;
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

    $changeset = $this->changesetID;
    $reference = $this->getChangeset()->getRenderingReference();

    for ($ii = $range_start; $ii < $range_start + $range_len; $ii++) {
      if (empty($mask[$ii])) {
        $gap = array_pop($gaps);
        $top = $gap[0];
        $len = $gap[1];

        $end   = $top + $len - 20;

        $contents = array();

        if ($len > 40) {
          $contents[] = javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'mustcapture' => true,
              'sigil'       => 'show-more',
              'meta'        => array(
                'id'    => $reference,
                'range' => "{$top}-{$len}/{$top}-20",
              ),
            ),
            "\xE2\x96\xB2 Show 20 Lines");
        }

        $contents[] = javelin_render_tag(
          'a',
          array(
            'href' => '#',
            'mustcapture' => true,
            'sigil'       => 'show-more',
            'meta'        => array(
              'id'    => $reference,
              'range' => "{$top}-{$len}/{$top}-{$len}",
            ),
          ),
          'Show All '.$len.' Lines');

        if ($len > 40) {
          $contents[] = javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'mustcapture' => true,
              'sigil'       => 'show-more',
              'meta'        => array(
                'id'    => $reference,
                'range' => "{$top}-{$len}/{$end}-20",
              ),
            ),
            "\xE2\x96\xBC Show 20 Lines");
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

      if ($o_num && $changeset) {
        $o_id = ' id="C'.$changeset.'OL'.$o_num.'"';
      } else {
        $o_id = null;
      }

      if ($n_num && $changeset) {
        $n_id = ' id="C'.$changeset.'NL'.$n_num.'"';
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

    $on_right = $this->isCommentInNewFile($comment);

    return id(new DifferentialInlineCommentView())
      ->setInlineComment($comment)
      ->setOnRight($on_right)
      ->setHandles($this->handles)
      ->setMarkupEngine($this->markupEngine)
      ->setEditable($edit)
      ->render();
  }

  protected function renderPropertyChangeHeader($changeset) {
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

    return null;
/*
  TODO

    $table = <table class="differential-property-table" />;
    $table->appendChild(
      <tr class="property-table-header">
        <th>Property Changes</th>
        <td class="oval">Old Value</td>
        <td class="nval">New Value</td>
      </tr>);

    $keys = array_keys($old + $new);
    sort($keys);
    foreach ($keys as $key) {
      $oval = idx($old, $key);
      $nval = idx($new, $key);
      if ($oval !== $nval) {
        if ($oval === null) {
          $oval = <em>null</em>;
        }
        if ($nval === null) {
          $nval = <em>null</em>;
        }
        $table->appendChild(
          <tr>
            <th>{$key}</th>
            <td class="oval">{$oval}</td>
            <td class="nval">{$nval}</td>
          </tr>);
      }
    }

    return $table;
*/
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

}
