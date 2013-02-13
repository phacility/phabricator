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
      $none = hsprintf('');
      switch ($change) {

        case DifferentialChangeType::TYPE_ADD:
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was <strong>added</strong>.', $none);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was <strong>added</strong>.', $none);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht(
                'This directory was <strong>added</strong>.',
                $none);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht(
                'This binary file was <strong>added</strong>.',
                $none);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht('This symlink was <strong>added</strong>.', $none);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht(
                'This submodule was <strong>added</strong>.',
                $none);
              break;
          }
          break;

        case DifferentialChangeType::TYPE_DELETE:
          switch ($file) {
            case DifferentialChangeType::FILE_TEXT:
              $message = pht('This file was <strong>deleted</strong>.', $none);
              break;
            case DifferentialChangeType::FILE_IMAGE:
              $message = pht('This image was <strong>deleted</strong>.', $none);
              break;
            case DifferentialChangeType::FILE_DIRECTORY:
              $message = pht(
                'This directory was <strong>deleted</strong>.',
                $none);
              break;
            case DifferentialChangeType::FILE_BINARY:
              $message = pht(
                'This binary file was <strong>deleted</strong>.',
                $none);
              break;
            case DifferentialChangeType::FILE_SYMLINK:
              $message = pht(
                'This symlink was <strong>deleted</strong>.',
                $none);
              break;
            case DifferentialChangeType::FILE_SUBMODULE:
              $message = pht(
                'This submodule was <strong>deleted</strong>.',
                $none);
              break;
          }
          break;

        case DifferentialChangeType::TYPE_MOVE_HERE:
          $from = phutil_tag('strong', array(), $changeset->getOldFile());
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
          $from = phutil_tag('strong', array(), $changeset->getOldFile());
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
          $paths = phutil_tag(
            'strong',
            array(),
            implode(', ', $changeset->getAwayPaths()));
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
          $paths = phutil_tag(
            'strong',
            array(),
            implode(', ', $changeset->getAwayPaths()));
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
          $paths = phutil_tag(
            'strong',
            array(),
            implode(', ', $changeset->getAwayPaths()));
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

    return hsprintf(
      '<div class="differential-meta-notice">%s</div>',
      $message);
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
          $oval = phutil_tag('em', array(), 'null');
        } else {
          $oval = phutil_escape_html_newlines($oval);
        }

        if ($nval === null) {
          $nval = phutil_tag('em', array(), 'null');
        } else {
          $nval = phutil_escape_html_newlines($nval);
        }

        $rows[] = hsprintf(
          '<tr>'.
            '<th>%s</th>'.
            '<td class="oval">%s</td>'.
            '<td class="nval">%s</td>'.
          '</tr>',
          $key,
          $oval,
          $nval);
      }
    }

    array_unshift($rows, hsprintf(
      '<tr class="property-table-header">'.
        '<th>%s</th>'.
        '<td class="oval">%s</td>'.
        '<td class="nval">%s</td>'.
      '</tr>',
      pht('Property Changes'),
      pht('Old Value'),
      pht('New Value')));

    return phutil_tag(
      'table',
      array('class' => 'differential-property-table'),
      $rows);
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

  protected function wrapChangeInTable($content) {
    if (!$content) {
      return null;
    }

    return javelin_tag(
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
