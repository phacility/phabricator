<?php

abstract class DifferentialChangesetRenderer {

  private $user;
  private $changeset;
  private $renderingReference;
  private $renderPropertyChangeHeader;
  private $missingOldLines;
  private $missingNewLines;
  private $oldLines;
  private $newLines;
  private $visibleLines;
  private $oldComments;
  private $newComments;
  private $oldChangesetID;
  private $newChangesetID;
  private $oldAttachesToNewFile;
  private $newAttachesToNewFile;
  private $highlightOld = array();
  private $highlightNew = array();
  private $linesOfContext;
  private $codeCoverage;
  private $handles;
  private $markupEngine;
  private $oldRender;
  private $newRender;
  private $originalOld;
  private $originalNew;

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

  public function setLinesOfContext($lines_of_context) {
    $this->linesOfContext = $lines_of_context;
    return $this;
  }
  protected function getLinesOfContext() {
    return $this->linesOfContext;
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

  public function setVisibleLines(array $visible_lines) {
    $this->visibleLines = $visible_lines;
    return $this;
  }
  protected function getVisibleLines() {
    return $this->visibleLines;
  }

  public function setNewLines(array $new_lines) {
    phlog(print_r($new_lines, true));
    $this->newLines = $new_lines;
    return $this;
  }
  protected function getNewLines() {
    return $this->newLines;
  }

  public function setOldLines(array $old_lines) {
    phlog(print_r($old_lines, true));
    $this->oldLines = $old_lines;
    return $this;
  }
  protected function getOldLines() {
    return $this->oldLines;
  }

  public function setMissingNewLines(array $missing_new_lines) {
    $this->missingNewLines = $missing_new_lines;
    return $this;
  }
  protected function getMissingNewLines() {
    return $this->missingNewLines;
  }

  public function setMissingOldLines(array $missing_old_lines) {
    $this->missingOldLines = $missing_old_lines;
    return $this;
  }
  protected function getMissingOldLines() {
    return $this->missingOldLines;
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

  abstract public function renderChangesetTable($contents);
  abstract public function renderTextChange(
    $range_start,
    $range_len,
    $mask_force,
    $feedback_mask
  );
  abstract public function renderFileChange(
    $old = null, 
    $new = null, 
    $id = 0, 
    $vs = 0
  );

  public function renderShield($message, $more) {

    if ($more) {
      $end = max(
        count($this->getOldLines()),
        count($this->getNewLines())
      );
      $reference = $this->getRenderingReference();
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
      '<td class="differential-shield" colspan="6">'.
        phutil_escape_html($message).
        $more.
      '</td>');
  }


  protected function renderPropertyChangeHeader($changeset) {
    if (!$this->shouldRenderPropertyChangeHeader()) {
      return null;
    }

    $old = $changeset->getOldProperties();
    $new = $changeset->getNewProperties();

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
          $oval = nl2br(phutil_escape_html($oval));
        }

        if ($nval === null) {
          $nval = '<em>null</em>';
        } else {
          $nval = nl2br(phutil_escape_html($nval));
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

  protected function renderChangeTypeHeader($changeset, $force) {
    $change = $changeset->getChangeType();
    $file = $changeset->getFileType();

    $message = null;
    if ($change == DifferentialChangeType::TYPE_CHANGE &&
        $file   == DifferentialChangeType::FILE_TEXT) {
      if ($force) {
        // We have to force something to render because there were no changes
        // of other kinds.
        $message = pht('This file was not modified.');
      } else {
        // Default case of changes to a text file, no metadata.
        return null;
      }
    } else {
      switch ($change) {

        case DifferentialChangeType::TYPE_ADD:
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was <strong>added</strong>.');
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was <strong>added</strong>.');
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This directory was <strong>added</strong>.');
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This binary file was <strong>added</strong>.');
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was <strong>added</strong>.');
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This submodule was <strong>added</strong>.');
              break;
          }
          break;

        case DifferentialChangeType::TYPE_DELETE:
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was <strong>deleted</strong>.');
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was <strong>deleted</strong>.');
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This directory was <strong>deleted</strong>.');
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This binary file was <strong>deleted</strong>.');
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was <strong>deleted</strong>.');
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This submodule was <strong>deleted</strong>.');
              break;
          }
          break;

        case DifferentialChangeType::TYPE_MOVE_HERE:
          $from =
            "<strong>".
              phutil_escape_html($changeset->getOldFile()).
            "</strong>";
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was moved from %s.', $from);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was moved from %s.', $from);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This directory was moved from %s.', $from);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This binary file was moved from %s.', $from);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was moved from %s.', $from);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This submodule was moved from %s.', $from);
              break;
          }
          break;

        case DifferentialChangeType::TYPE_COPY_HERE:
          $from =
            "<strong>".
              phutil_escape_html($changeset->getOldFile()).
            "</strong>";
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was copied from %s.', $from);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was copied from %s.', $from);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This directory was copied from %s.', $from);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This binary file was copied from %s.', $from);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was copied from %s.', $from);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This submodule was copied from %s.', $from);
              break;
          }
          break;

        case DifferentialChangeType::TYPE_MOVE_AWAY:
          $paths =
            "<strong>".
              phutil_escape_html(implode(', ', $changeset->getAwayPaths())).
            "</strong>";
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was moved to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was moved to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This directory was moved to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This binary file was moved to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was moved to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This submodule was moved to %s.', $paths);
              break;
          }
          break;

        case DifferentialChangeType::TYPE_COPY_AWAY:
          $paths =
            "<strong>".
              phutil_escape_html(implode(', ', $changeset->getAwayPaths())).
            "</strong>";
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was copied to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was copied to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This directory was copied to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This binary file was copied to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was copied to %s.', $paths);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This submodule was copied to %s.', $paths);
              break;
          }
          break;

        case DifferentialChangeType::TYPE_MULTICOPY:
          $paths =
            "<strong>".
              phutil_escape_html(implode(', ', $changeset->getAwayPaths())).
            "</strong>";
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht(
                'This file was deleted after being copied to %s.',
                $paths);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht(
                'This image was deleted after being copied to %s.',
                $paths);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht(
                'This directory was deleted after being copied to %s.',
                $paths);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht(
                'This binary file was deleted after being copied to %s.',
                $paths);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht(
                'This symlink was deleted after being copied to %s.',
                $paths);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht(
                'This submodule was deleted after being copied to %s.',
                $paths);
              break;
          }
          break;

        default:
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This is a file.');
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This is an image.');
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht('This is a directory.');
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht('This is a binary file.');
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This is a symlink.');
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht('This is a submodule.');
              break;
          }
          break;
      }
    }

    return
      '<div class="differential-meta-notice">'.
        $message.
      '</div>';
  }

  protected function renderInlineComment(
    PhabricatorInlineCommentInterface $comment,
    $on_right = false) {

    $user = $this->getUser();
    $edit = $user &&
            ($comment->getAuthorPHID() == $user->getPHID()) &&
            ($comment->isDraft());
    $allow_reply = (bool)$user;

    return id(new DifferentialInlineCommentView())
      ->setInlineComment($comment)
      ->setOnRight($on_right)
      ->setHandles($this->getHandles())
      ->setMarkupEngine($this->getMarkupEngine())
      ->setEditable($edit)
      ->setAllowReply($allow_reply)
      ->render();
  }

}
