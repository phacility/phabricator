<?php

abstract class DifferentialChangesetHTMLRenderer
  extends DifferentialChangesetRenderer {

  protected function renderChangeTypeHeader($force) {
    $changeset = $this->getChangeset();

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

  protected function renderPropertyChangeHeader() {
    $changeset = $this->getChangeset();

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
          '<th>'.pht('Property Changes').'</th>'.
          '<td class="oval">'.pht('Old Value').'</td>'.
          '<td class="nval">'.pht('New Value').'</td>'.
        '</tr>'.
        implode('', $rows).
      '</table>';
  }

  public function renderShield($message, $force = 'default') {
    $end = count($this->getOldLines());
    $reference = $this->getRenderingReference();

    if ($force !== 'text' &&
        $force !== 'whitespace' &&
        $force !== 'none' &&
        $force !== 'default') {
      throw new Exception("Invalid 'force' parameter '{$force}'!");
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

    if ($force == 'whitespace') {
      $meta['whitespace'] = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;
    }

    $more = null;
    if ($force !== 'none') {
      $more = ' '.javelin_render_tag(
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
      javelin_render_tag(
        'tr',
        array(
          'sigil' => 'context-target',
        ),
        '<td class="differential-shield" colspan="6">'.
          phutil_escape_html($message).
          $more.
        '</td>'));
  }

  protected function wrapChangeInTable($content) {
    if (!$content) {
      return null;
    }

    return javelin_render_tag(
      'table',
      array(
        'class' => 'differential-diff remarkup-code PhabricatorMonospaced',
        'sigil' => 'differential-diff',
      ),
      $content);
  }

  protected function renderInlineComment(
    PhabricatorInlineCommentInterface $comment,
    $on_right = false) {

    return $this->buildInlineComment($comment, $on_right)->render();
  }

  protected function buildInlineComment(
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
      ->setAllowReply($allow_reply);
  }

}
