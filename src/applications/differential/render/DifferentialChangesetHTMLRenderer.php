<?php

abstract class DifferentialChangesetHTMLRenderer
  extends DifferentialChangesetRenderer {

  public static function getHTMLRendererByKey($key) {
    switch ($key) {
      case '1up':
        return new DifferentialChangesetOneUpRenderer();
      case '2up':
      default:
        return new DifferentialChangesetTwoUpRenderer();
    }
    throw new Exception(pht('Unknown HTML renderer "%s"!', $key));
  }

  abstract protected function getRendererTableClass();
  abstract public function getRowScaffoldForInline(
    PHUIDiffInlineCommentView $view);

  protected function renderChangeTypeHeader($force) {
    $changeset = $this->getChangeset();

    $change = $changeset->getChangeType();
    $file = $changeset->getFileType();

    $messages = array();
    switch ($change) {

      case DifferentialChangeType::TYPE_ADD:
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht('This file was added.');
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This image was added.');
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This directory was added.');
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This binary file was added.');
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This symlink was added.');
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This submodule was added.');
            break;
        }
        break;

      case DifferentialChangeType::TYPE_DELETE:
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht('This file was deleted.');
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This image was deleted.');
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This directory was deleted.');
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This binary file was deleted.');
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This symlink was deleted.');
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This submodule was deleted.');
            break;
        }
        break;

      case DifferentialChangeType::TYPE_MOVE_HERE:
        $from = phutil_tag('strong', array(), $changeset->getOldFile());
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht('This file was moved from %s.', $from);
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This image was moved from %s.', $from);
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This directory was moved from %s.', $from);
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This binary file was moved from %s.', $from);
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This symlink was moved from %s.', $from);
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This submodule was moved from %s.', $from);
            break;
        }
        break;

      case DifferentialChangeType::TYPE_COPY_HERE:
        $from = phutil_tag('strong', array(), $changeset->getOldFile());
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht('This file was copied from %s.', $from);
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This image was copied from %s.', $from);
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This directory was copied from %s.', $from);
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This binary file was copied from %s.', $from);
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This symlink was copied from %s.', $from);
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This submodule was copied from %s.', $from);
            break;
        }
        break;

      case DifferentialChangeType::TYPE_MOVE_AWAY:
        $paths = phutil_tag(
          'strong',
          array(),
          implode(', ', $changeset->getAwayPaths()));
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht('This file was moved to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This image was moved to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This directory was moved to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This binary file was moved to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This symlink was moved to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This submodule was moved to %s.', $paths);
            break;
        }
        break;

      case DifferentialChangeType::TYPE_COPY_AWAY:
        $paths = phutil_tag(
          'strong',
          array(),
          implode(', ', $changeset->getAwayPaths()));
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht('This file was copied to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This image was copied to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This directory was copied to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This binary file was copied to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This symlink was copied to %s.', $paths);
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This submodule was copied to %s.', $paths);
            break;
        }
        break;

      case DifferentialChangeType::TYPE_MULTICOPY:
        $paths = phutil_tag(
          'strong',
          array(),
          implode(', ', $changeset->getAwayPaths()));
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            $messages[] = pht(
              'This file was deleted after being copied to %s.',
              $paths);
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht(
              'This image was deleted after being copied to %s.',
              $paths);
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht(
              'This directory was deleted after being copied to %s.',
              $paths);
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht(
              'This binary file was deleted after being copied to %s.',
              $paths);
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht(
              'This symlink was deleted after being copied to %s.',
              $paths);
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht(
              'This submodule was deleted after being copied to %s.',
              $paths);
            break;
        }
        break;

      default:
        switch ($file) {
          case DifferentialChangeType::FILE_TEXT:
            // This is the default case, so we only render this header if
            // forced to since it's not very useful.
            if ($force) {
              $messages[] = pht('This file was not modified.');
            }
            break;
          case DifferentialChangeType::FILE_IMAGE:
            $messages[] = pht('This is an image.');
            break;
          case DifferentialChangeType::FILE_DIRECTORY:
            $messages[] = pht('This is a directory.');
            break;
          case DifferentialChangeType::FILE_BINARY:
            $messages[] = pht('This is a binary file.');
            break;
          case DifferentialChangeType::FILE_SYMLINK:
            $messages[] = pht('This is a symlink.');
            break;
          case DifferentialChangeType::FILE_SUBMODULE:
            $messages[] = pht('This is a submodule.');
            break;
        }
        break;
    }

    return $this->formatHeaderMessages($messages);
  }

  protected function renderUndershieldHeader() {
    $messages = array();

    $changeset = $this->getChangeset();

    $file = $changeset->getFileType();

    // If this is a text file with at least one hunk, we may have converted
    // the text encoding. In this case, show a note.
    $show_encoding = ($file == DifferentialChangeType::FILE_TEXT) &&
                     ($changeset->getHunks());

    if ($show_encoding) {
      $encoding = $this->getOriginalCharacterEncoding();
      if ($encoding != 'utf8') {
        if ($encoding) {
          $messages[] = pht(
            'This file was converted from %s for display.',
            phutil_tag('strong', array(), $encoding));
        } else {
          $messages[] = pht('This file uses an unknown character encoding.');
        }
      }
    }

    $blocks = $this->getDocumentEngineBlocks();
    if ($blocks) {
      foreach ($blocks->getMessages() as $message) {
        $messages[] = $message;
      }
    } else {
      if ($this->getHighlightingDisabled()) {
        $byte_limit = DifferentialChangesetParser::HIGHLIGHT_BYTE_LIMIT;
        $byte_limit = phutil_format_bytes($byte_limit);
        $messages[] = pht(
          'This file is larger than %s, so syntax highlighting is '.
          'disabled by default.',
          $byte_limit);
      }
    }

    return $this->formatHeaderMessages($messages);
  }

  private function formatHeaderMessages(array $messages) {
    if (!$messages) {
      return null;
    }

    foreach ($messages as $key => $message) {
      $messages[$key] = phutil_tag('li', array(), $message);
    }

    return phutil_tag(
      'ul',
      array(
        'class' => 'differential-meta-notice',
      ),
      $messages);
  }

  protected function renderPropertyChangeHeader() {
    $changeset = $this->getChangeset();
    list($old, $new) = $this->getChangesetProperties($changeset);

    // If we don't have any property changes, don't render this table.
    if ($old === $new) {
      return null;
    }

    $keys = array_keys($old + $new);
    sort($keys);

    $key_map = array(
      'unix:filemode' => pht('File Mode'),
      'file:dimensions' => pht('Image Dimensions'),
      'file:mimetype' => pht('MIME Type'),
      'file:size' => pht('File Size'),
    );

    $rows = array();
    foreach ($keys as $key) {
      $oval = idx($old, $key);
      $nval = idx($new, $key);
      if ($oval !== $nval) {
        if ($oval === null) {
          $oval = phutil_tag('em', array(), 'null');
        } else {
          $oval = phutil_escape_html_newlines($oval);
        }

        if ($nval === null) {
          $nval = phutil_tag('em', array(), 'null');
        } else {
          $nval = phutil_escape_html_newlines($nval);
        }

        $readable_key = idx($key_map, $key, $key);

        $row = array(
          $readable_key,
          $oval,
          $nval,
        );
        $rows[] = $row;

      }
    }

    $classes = array('', 'oval', 'nval');
    $headers = array(
      pht('Property'),
      pht('Old Value'),
      pht('New Value'),
    );
    $table = id(new AphrontTableView($rows))
      ->setHeaders($headers)
      ->setColumnClasses($classes);
    return phutil_tag(
      'div',
      array(
        'class' => 'differential-property-table',
      ),
      $table);
  }

  public function renderShield($message, $force = 'default') {
    $end = count($this->getOldLines());
    $reference = $this->getRenderingReference();

    if ($force !== 'text' &&
        $force !== 'none' &&
        $force !== 'default') {
      throw new Exception(
        pht(
          "Invalid '%s' parameter '%s'!",
          'force',
          $force));
    }

    $range = "0-{$end}";
    if ($force == 'text') {
      // If we're forcing text, force the whole file to be rendered.
      $range = "{$range}/0-{$end}";
    }

    $meta = array(
      'ref'   => $reference,
      'range' => $range,
    );

    $content = array();
    $content[] = $message;
    if ($force !== 'none') {
      $content[] = ' ';
      $content[] = javelin_tag(
        'a',
        array(
          'mustcapture' => true,
          'sigil'       => 'show-more',
          'class'       => 'complete',
          'href'        => '#',
          'meta'        => $meta,
        ),
        pht('Show File Contents'));
    }

    return $this->wrapChangeInTable(
      javelin_tag(
        'tr',
        array(
          'sigil' => 'context-target',
        ),
        phutil_tag(
          'td',
          array(
            'class' => 'differential-shield',
            'colspan' => 6,
          ),
          $content)));
  }

  abstract protected function renderColgroup();


  protected function wrapChangeInTable($content) {
    if (!$content) {
      return null;
    }

    $classes = array();
    $classes[] = 'differential-diff';
    $classes[] = 'remarkup-code';
    $classes[] = 'PhabricatorMonospaced';
    $classes[] = $this->getRendererTableClass();

    $sigils = array();
    $sigils[] = 'differential-diff';
    foreach ($this->getTableSigils() as $sigil) {
      $sigils[] = $sigil;
    }

    return javelin_tag(
      'table',
      array(
        'class' => implode(' ', $classes),
        'sigil' => implode(' ', $sigils),
      ),
      array(
        $this->renderColgroup(),
        $content,
      ));
  }

  protected function getTableSigils() {
    return array();
  }

  protected function buildInlineComment(
    PhabricatorInlineComment $comment,
    $on_right = false) {

    $viewer = $this->getUser();
    $edit = $viewer &&
            ($comment->getAuthorPHID() == $viewer->getPHID()) &&
            ($comment->isDraft())
            && $this->getShowEditAndReplyLinks();
    $allow_reply = (bool)$viewer && $this->getShowEditAndReplyLinks();
    $allow_done = !$comment->isDraft() && $this->getCanMarkDone();

    return id(new PHUIDiffInlineCommentDetailView())
      ->setViewer($viewer)
      ->setInlineComment($comment)
      ->setIsOnRight($on_right)
      ->setHandles($this->getHandles())
      ->setMarkupEngine($this->getMarkupEngine())
      ->setEditable($edit)
      ->setAllowReply($allow_reply)
      ->setCanMarkDone($allow_done)
      ->setObjectOwnerPHID($this->getObjectOwnerPHID());
  }


  /**
   * Build links which users can click to show more context in a changeset.
   *
   * @param int Beginning of the line range to build links for.
   * @param int Length of the line range to build links for.
   * @param int Total number of lines in the changeset.
   * @return markup Rendered links.
   */
  protected function renderShowContextLinks(
    $top,
    $len,
    $changeset_length,
    $is_blocks = false) {

    $block_size = 20;
    $end = ($top + $len) - $block_size;

    // If this is a large block, such that the "top" and "bottom" ranges are
    // non-overlapping, we'll provide options to show the top, bottom or entire
    // block. For smaller blocks, we only provide an option to show the entire
    // block, since it would be silly to show the bottom 20 lines of a 25-line
    // block.
    $is_large_block = ($len > ($block_size * 2));

    $links = array();

    $block_display = new PhutilNumber($block_size);

    if ($is_large_block) {
      $is_first_block = ($top == 0);
      if ($is_first_block) {
        if ($is_blocks) {
          $text = pht('Show First %s Block(s)', $block_display);
        } else {
          $text = pht('Show First %s Line(s)', $block_display);
        }
      } else {
        if ($is_blocks) {
          $text = pht("\xE2\x96\xB2 Show %s Block(s)", $block_display);
        } else {
          $text = pht("\xE2\x96\xB2 Show %s Line(s)", $block_display);
        }
      }

      $links[] = $this->renderShowContextLink(
        false,
        "{$top}-{$len}/{$top}-20",
        $text);
    }

    if ($is_blocks) {
      $text = pht('Show All %s Block(s)', new PhutilNumber($len));
    } else {
      $text = pht('Show All %s Line(s)', new PhutilNumber($len));
    }

    $links[] = $this->renderShowContextLink(
      true,
      "{$top}-{$len}/{$top}-{$len}",
      $text);

    if ($is_large_block) {
      $is_last_block = (($top + $len) >= $changeset_length);
      if ($is_last_block) {
        if ($is_blocks) {
          $text = pht('Show Last %s Block(s)', $block_display);
        } else {
          $text = pht('Show Last %s Line(s)', $block_display);
        }
      } else {
        if ($is_blocks) {
          $text = pht("\xE2\x96\xBC Show %s Block(s)", $block_display);
        } else {
          $text = pht("\xE2\x96\xBC Show %s Line(s)", $block_display);
        }
      }

      $links[] = $this->renderShowContextLink(
        false,
        "{$top}-{$len}/{$end}-20",
        $text);
    }

    return phutil_implode_html(" \xE2\x80\xA2 ", $links);
  }


  /**
   * Build a link that shows more context in a changeset.
   *
   * See @{method:renderShowContextLinks}.
   *
   * @param bool Does this link show all context when clicked?
   * @param string Range specification for lines to show.
   * @param string Text of the link.
   * @return markup Rendered link.
   */
  private function renderShowContextLink($is_all, $range, $text) {
    $reference = $this->getRenderingReference();

    return javelin_tag(
      'a',
      array(
        'href' => '#',
        'mustcapture' => true,
        'sigil' => 'show-more',
        'meta' => array(
          'type' => ($is_all ? 'all' : null),
          'range' => $range,
        ),
      ),
      $text);
  }

  /**
   * Build the prefixes for line IDs used to track inline comments.
   *
   * @return pair<wild, wild> Left and right prefixes.
   */
  protected function getLineIDPrefixes() {
    // These look like "C123NL45", which means the line is line 45 on the
    // "new" side of the file in changeset 123.

    // The "C" stands for "changeset", and is followed by a changeset ID.

    // "N" stands for "new" and means the comment should attach to the new file
    // when stored. "O" stands for "old" and means the comment should attach to
    // the old file. These are important because either the old or new part
    // of a file may appear on the left or right side of the diff in the
    // diff-of-diffs view.

    // The "L" stands for "line" and is followed by the line number.

    if ($this->getOldChangesetID()) {
      $left_prefix = array();
      $left_prefix[] = 'C';
      $left_prefix[] = $this->getOldChangesetID();
      $left_prefix[] = $this->getOldAttachesToNewFile() ? 'N' : 'O';
      $left_prefix[] = 'L';
      $left_prefix = implode('', $left_prefix);
    } else {
      $left_prefix = null;
    }

    if ($this->getNewChangesetID()) {
      $right_prefix = array();
      $right_prefix[] = 'C';
      $right_prefix[] = $this->getNewChangesetID();
      $right_prefix[] = $this->getNewAttachesToNewFile() ? 'N' : 'O';
      $right_prefix[] = 'L';
      $right_prefix = implode('', $right_prefix);
    } else {
      $right_prefix = null;
    }

    return array($left_prefix, $right_prefix);
  }

}
