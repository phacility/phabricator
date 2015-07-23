<?php

abstract class DifferentialChangesetRenderer extends Phobject {

  private $user;
  private $changeset;
  private $renderingReference;
  private $renderPropertyChangeHeader;
  private $isTopLevel;
  private $isUndershield;
  private $hunkStartLines;
  private $oldLines;
  private $newLines;
  private $oldComments;
  private $newComments;
  private $oldChangesetID;
  private $newChangesetID;
  private $oldAttachesToNewFile;
  private $newAttachesToNewFile;
  private $highlightOld = array();
  private $highlightNew = array();
  private $codeCoverage;
  private $handles;
  private $markupEngine;
  private $oldRender;
  private $newRender;
  private $originalOld;
  private $originalNew;
  private $gaps;
  private $mask;
  private $depths;
  private $originalCharacterEncoding;
  private $showEditAndReplyLinks;
  private $canMarkDone;
  private $objectOwnerPHID;
  private $highlightingDisabled;

  private $oldFile = false;
  private $newFile = false;

  abstract public function getRendererKey();

  public function setShowEditAndReplyLinks($bool) {
    $this->showEditAndReplyLinks = $bool;
    return $this;
  }

  public function getShowEditAndReplyLinks() {
    return $this->showEditAndReplyLinks;
  }

  public function setHighlightingDisabled($highlighting_disabled) {
    $this->highlightingDisabled = $highlighting_disabled;
    return $this;
  }

  public function getHighlightingDisabled() {
    return $this->highlightingDisabled;
  }

  public function setOriginalCharacterEncoding($original_character_encoding) {
    $this->originalCharacterEncoding = $original_character_encoding;
    return $this;
  }

  public function getOriginalCharacterEncoding() {
    return $this->originalCharacterEncoding;
  }

  public function setIsUndershield($is_undershield) {
    $this->isUndershield = $is_undershield;
    return $this;
  }

  public function getIsUndershield() {
    return $this->isUndershield;
  }

  public function setDepths($depths) {
    $this->depths = $depths;
    return $this;
  }
  protected function getDepths() {
    return $this->depths;
  }

  public function setMask($mask) {
    $this->mask = $mask;
    return $this;
  }
  protected function getMask() {
    return $this->mask;
  }

  public function setGaps($gaps) {
    $this->gaps = $gaps;
    return $this;
  }
  protected function getGaps() {
    return $this->gaps;
  }

  public function attachOldFile(PhabricatorFile $old = null) {
    $this->oldFile = $old;
    return $this;
  }

  public function getOldFile() {
    if ($this->oldFile === false) {
      throw new PhabricatorDataNotAttachedException($this);
    }
    return $this->oldFile;
  }

  public function hasOldFile() {
    return (bool)$this->oldFile;
  }

  public function attachNewFile(PhabricatorFile $new = null) {
    $this->newFile = $new;
    return $this;
  }

  public function getNewFile() {
    if ($this->newFile === false) {
      throw new PhabricatorDataNotAttachedException($this);
    }
    return $this->newFile;
  }

  public function hasNewFile() {
    return (bool)$this->newFile;
  }

  public function setOriginalNew($original_new) {
    $this->originalNew = $original_new;
    return $this;
  }
  protected function getOriginalNew() {
    return $this->originalNew;
  }

  public function setOriginalOld($original_old) {
    $this->originalOld = $original_old;
    return $this;
  }
  protected function getOriginalOld() {
    return $this->originalOld;
  }

  public function setNewRender($new_render) {
    $this->newRender = $new_render;
    return $this;
  }
  protected function getNewRender() {
    return $this->newRender;
  }

  public function setOldRender($old_render) {
    $this->oldRender = $old_render;
    return $this;
  }
  protected function getOldRender() {
    return $this->oldRender;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }
  public function getMarkupEngine() {
    return $this->markupEngine;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }
  protected function getHandles() {
    return $this->handles;
  }

  public function setCodeCoverage($code_coverage) {
    $this->codeCoverage = $code_coverage;
    return $this;
  }
  protected function getCodeCoverage() {
    return $this->codeCoverage;
  }

  public function setHighlightNew($highlight_new) {
    $this->highlightNew = $highlight_new;
    return $this;
  }
  protected function getHighlightNew() {
    return $this->highlightNew;
  }

  public function setHighlightOld($highlight_old) {
    $this->highlightOld = $highlight_old;
    return $this;
  }
  protected function getHighlightOld() {
    return $this->highlightOld;
  }

  public function setNewAttachesToNewFile($attaches) {
    $this->newAttachesToNewFile = $attaches;
    return $this;
  }
  protected function getNewAttachesToNewFile() {
    return $this->newAttachesToNewFile;
  }

  public function setOldAttachesToNewFile($attaches) {
    $this->oldAttachesToNewFile = $attaches;
    return $this;
  }
  protected function getOldAttachesToNewFile() {
    return $this->oldAttachesToNewFile;
  }

  public function setNewChangesetID($new_changeset_id) {
    $this->newChangesetID = $new_changeset_id;
    return $this;
  }
  protected function getNewChangesetID() {
    return $this->newChangesetID;
  }

  public function setOldChangesetID($old_changeset_id) {
    $this->oldChangesetID = $old_changeset_id;
    return $this;
  }
  protected function getOldChangesetID() {
    return $this->oldChangesetID;
  }

  public function setNewComments(array $new_comments) {
    foreach ($new_comments as $line_number => $comments) {
      assert_instances_of($comments, 'PhabricatorInlineCommentInterface');
    }
    $this->newComments = $new_comments;
    return $this;
  }
  protected function getNewComments() {
    return $this->newComments;
  }

  public function setOldComments(array $old_comments) {
    foreach ($old_comments as $line_number => $comments) {
      assert_instances_of($comments, 'PhabricatorInlineCommentInterface');
    }
    $this->oldComments = $old_comments;
    return $this;
  }
  protected function getOldComments() {
    return $this->oldComments;
  }

  public function setNewLines(array $new_lines) {
    $this->newLines = $new_lines;
    return $this;
  }
  protected function getNewLines() {
    return $this->newLines;
  }

  public function setOldLines(array $old_lines) {
    $this->oldLines = $old_lines;
    return $this;
  }
  protected function getOldLines() {
    return $this->oldLines;
  }

  public function setHunkStartLines(array $hunk_start_lines) {
    $this->hunkStartLines = $hunk_start_lines;
    return $this;
  }

  protected function getHunkStartLines() {
    return $this->hunkStartLines;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  protected function getUser() {
    return $this->user;
  }

  public function setChangeset(DifferentialChangeset $changeset) {
    $this->changeset = $changeset;
    return $this;
  }
  protected function getChangeset() {
    return $this->changeset;
  }

  public function setRenderingReference($rendering_reference) {
    $this->renderingReference = $rendering_reference;
    return $this;
  }
  protected function getRenderingReference() {
    return $this->renderingReference;
  }

  public function setRenderPropertyChangeHeader($should_render) {
    $this->renderPropertyChangeHeader = $should_render;
    return $this;
  }

  private function shouldRenderPropertyChangeHeader() {
    return $this->renderPropertyChangeHeader;
  }

  public function setIsTopLevel($is) {
    $this->isTopLevel = $is;
    return $this;
  }

  private function getIsTopLevel() {
    return $this->isTopLevel;
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

  final public function renderChangesetTable($content) {
    $props = null;
    if ($this->shouldRenderPropertyChangeHeader()) {
      $props = $this->renderPropertyChangeHeader();
    }

    $notice = null;
    if ($this->getIsTopLevel()) {
      $force = (!$content && !$props);
      $notice = $this->renderChangeTypeHeader($force);
    }

    $undershield = null;
    if ($this->getIsUndershield()) {
      $undershield = $this->renderUndershieldHeader();
    }

    $result = $notice.$props.$undershield.$content;

    // TODO: Let the user customize their tab width / display style.
    // TODO: We should possibly post-process "\r" as well.
    // TODO: Both these steps should happen earlier.
    $result = str_replace("\t", '  ', $result);

    return phutil_safe_html($result);
  }

  abstract public function isOneUpRenderer();
  abstract public function renderTextChange(
    $range_start,
    $range_len,
    $rows);
  abstract public function renderFileChange(
    $old = null,
    $new = null,
    $id = 0,
    $vs = 0);

  abstract protected function renderChangeTypeHeader($force);
  abstract protected function renderUndershieldHeader();

  protected function didRenderChangesetTableContents($contents) {
    return $contents;
  }

  /**
   * Render a "shield" over the diff, with a message like "This file is
   * generated and does not need to be reviewed." or "This file was completely
   * deleted." This UI element hides unimportant text so the reviewer doesn't
   * need to scroll past it.
   *
   * The shield includes a link to view the underlying content. This link
   * may force certain rendering modes when the link is clicked:
   *
   *    - `"default"`: Render the diff normally, as though it was not
   *      shielded. This is the default and appropriate if the underlying
   *      diff is a normal change, but was hidden for reasons of not being
   *      important (e.g., generated code).
   *    - `"text"`: Force the text to be shown. This is probably only relevant
   *      when a file is not changed.
   *    - `"whitespace"`: Force the text to be shown, and the diff to be
   *      rendered with all whitespace shown. This is probably only relevant
   *      when a file is changed only by altering whitespace.
   *    - `"none"`: Don't show the link (e.g., text not available).
   *
   * @param   string        Message explaining why the diff is hidden.
   * @param   string|null   Force mode, see above.
   * @return  string        Shield markup.
   */
  abstract public function renderShield($message, $force = 'default');

  abstract protected function renderPropertyChangeHeader();

  protected function buildPrimitives($range_start, $range_len) {
    $primitives = array();

    $hunk_starts = $this->getHunkStartLines();

    $mask = $this->getMask();
    $gaps = $this->getGaps();

    $old = $this->getOldLines();
    $new = $this->getNewLines();
    $old_render = $this->getOldRender();
    $new_render = $this->getNewRender();
    $old_comments = $this->getOldComments();
    $new_comments = $this->getNewComments();

    $size = count($old);
    for ($ii = $range_start; $ii < $range_start + $range_len; $ii++) {
      if (empty($mask[$ii])) {
        list($top, $len) = array_pop($gaps);
        $primitives[] = array(
          'type' => 'context',
          'top' => $top,
          'len' => $len,
        );

        $ii += ($len - 1);
        continue;
      }

      $ospec = array(
        'type' => 'old',
        'htype' => null,
        'cursor' => $ii,
        'line' => null,
        'oline' => null,
        'render' => null,
      );

      $nspec = array(
        'type' => 'new',
        'htype' => null,
        'cursor' => $ii,
        'line' => null,
        'oline' => null,
        'render' => null,
        'copy' => null,
        'coverage' => null,
      );

      if (isset($old[$ii])) {
        $ospec['line'] = (int)$old[$ii]['line'];
        $nspec['oline'] = (int)$old[$ii]['line'];
        $ospec['htype'] = $old[$ii]['type'];
        if (isset($old_render[$ii])) {
          $ospec['render'] = $old_render[$ii];
        }
      }

      if (isset($new[$ii])) {
        $nspec['line'] = (int)$new[$ii]['line'];
        $ospec['oline'] = (int)$new[$ii]['line'];
        $nspec['htype'] = $new[$ii]['type'];
        if (isset($new_render[$ii])) {
          $nspec['render'] = $new_render[$ii];
        }
      }

      if (isset($hunk_starts[$ospec['line']])) {
        $primitives[] = array(
          'type' => 'no-context',
        );
      }

      $primitives[] = $ospec;
      $primitives[] = $nspec;

      if ($ospec['line'] !== null && isset($old_comments[$ospec['line']])) {
        foreach ($old_comments[$ospec['line']] as $comment) {
          $primitives[] = array(
            'type' => 'inline',
            'comment' => $comment,
            'right' => false,
          );
        }
      }

      if ($nspec['line'] !== null && isset($new_comments[$nspec['line']])) {
        foreach ($new_comments[$nspec['line']] as $comment) {
          $primitives[] = array(
            'type' => 'inline',
            'comment' => $comment,
            'right' => true,
          );
        }
      }

      if ($hunk_starts && ($ii == $size - 1)) {
        $primitives[] = array(
          'type' => 'no-context',
        );
      }
    }

    if ($this->isOneUpRenderer()) {
      $primitives = $this->processPrimitivesForOneUp($primitives);
    }

    return $primitives;
  }

  private function processPrimitivesForOneUp(array $primitives) {
    // Primitives come out of buildPrimitives() in two-up format, because it
    // is the most general, flexible format. To put them into one-up format,
    // we need to filter and reorder them. In particular:
    //
    //   - We discard unchanged lines in the old file; in one-up format, we
    //     render them only once.
    //   - We group contiguous blocks of old-modified and new-modified lines, so
    //     they render in "block of old, block of new" order instead of
    //     alternating old and new lines.

    $out = array();

    $old_buf = array();
    $new_buf = array();
    foreach ($primitives as $primitive) {
      $type = $primitive['type'];

      if ($type == 'old') {
        if (!$primitive['htype']) {
          // This is a line which appears in both the old file and the new
          // file, or the spacer corresponding to a line added in the new file.
          // Ignore it when rendering a one-up diff.
          continue;
        }
        $old_buf[] = $primitive;
      } else if ($type == 'new') {
        if ($primitive['line'] === null) {
          // This is an empty spacer corresponding to a line removed from the
          // old file. Ignore it when rendering a one-up diff.
          continue;
        }
        if (!$primitive['htype']) {
          // If this line is the same in both versions of the file, put it in
          // the old line buffer. This makes sure inlines on old, unchanged
          // lines end up in the right place.

          // First, we need to flush the line buffers if they're not empty.
          if ($old_buf) {
            $out[] = $old_buf;
            $old_buf = array();
          }
          if ($new_buf) {
            $out[] = $new_buf;
            $new_buf = array();
          }
          $old_buf[] = $primitive;
        } else {
          $new_buf[] = $primitive;
        }
      } else if ($type == 'context' || $type == 'no-context') {
        $out[] = $old_buf;
        $out[] = $new_buf;
        $old_buf = array();
        $new_buf = array();
        $out[] = array($primitive);
      } else if ($type == 'inline') {

        // If this inline is on the left side, put it after the old lines.
        if (!$primitive['right']) {
          $out[] = $old_buf;
          $out[] = array($primitive);
          $old_buf = array();
        } else {
          $out[] = $old_buf;
          $out[] = $new_buf;
          $out[] = array($primitive);
          $old_buf = array();
          $new_buf = array();
        }

      } else {
        throw new Exception(pht("Unknown primitive type '%s'!", $primitive));
      }
    }

    $out[] = $old_buf;
    $out[] = $new_buf;
    $out = array_mergev($out);

    return $out;
  }

  protected function getChangesetProperties($changeset) {
    $old = $changeset->getOldProperties();
    $new = $changeset->getNewProperties();

    // When adding files, don't show the uninteresting 644 filemode change.
    if ($changeset->getChangeType() == DifferentialChangeType::TYPE_ADD &&
        $new == array('unix:filemode' => '100644')) {
      unset($new['unix:filemode']);
    }

    // Likewise when removing files.
    if ($changeset->getChangeType() == DifferentialChangeType::TYPE_DELETE &&
        $old == array('unix:filemode' => '100644')) {
      unset($old['unix:filemode']);
    }

    if ($this->hasOldFile()) {
      $file = $this->getOldFile();
      if ($file->getImageWidth()) {
        $dimensions = $file->getImageWidth().'x'.$file->getImageHeight();
        $old['file:dimensions'] = $dimensions;
      }
      $old['file:mimetype'] = $file->getMimeType();
      $old['file:size'] = phutil_format_bytes($file->getByteSize());
    }

    if ($this->hasNewFile()) {
      $file = $this->getNewFile();
      if ($file->getImageWidth()) {
        $dimensions = $file->getImageWidth().'x'.$file->getImageHeight();
        $new['file:dimensions'] = $dimensions;
      }
      $new['file:mimetype'] = $file->getMimeType();
      $new['file:size'] = phutil_format_bytes($file->getByteSize());
    }

    return array($old, $new);
  }

  public function renderUndoTemplates() {
    $views = array(
      'l' => id(new PHUIDiffInlineCommentUndoView())->setIsOnRight(false),
      'r' => id(new PHUIDiffInlineCommentUndoView())->setIsOnRight(true),
    );

    foreach ($views as $key => $view) {
      $scaffold = $this->getRowScaffoldForInline($view);
      $views[$key] = id(new PHUIDiffInlineCommentTableScaffold())
        ->addRowScaffold($scaffold);
    }

    return $views;
  }

}
