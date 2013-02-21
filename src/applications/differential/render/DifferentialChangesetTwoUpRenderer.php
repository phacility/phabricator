<?php

final class DifferentialChangesetTwoUpRenderer
  extends DifferentialChangesetHTMLRenderer {

  public function isOneUpRenderer() {
    return false;
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $hunk_starts = $this->getHunkStartLines();

    $context_not_available = null;
    if ($hunk_starts) {
      $context_not_available = javelin_tag(
        'tr',
        array(
          'sigil' => 'context-target',
        ),
        phutil_tag(
          'td',
          array(
            'colspan' => 6,
            'class' => 'show-more'
          ),
          pht('Context not available.')));
    }

    $html = array();
    $old_lines = $this->getOldLines();
    $new_lines = $this->getNewLines();
    $gaps = $this->getGaps();
    $reference = $this->getRenderingReference();
    $left_id = $this->getOldChangesetID();
    $right_id = $this->getNewChangesetID();

    // "N" stands for 'new' and means the comment should attach to the new file
    // when stored, i.e. DifferentialInlineComment->setIsNewFile().
    // "O" stands for 'old' and means the comment should attach to the old file.

    $left_char = $this->getOldAttachesToNewFile()
      ? 'N'
      : 'O';
    $right_char = $this->getNewAttachesToNewFile()
      ? 'N'
      : 'O';

    $changeset = $this->getChangeset();
    $copy_lines = idx($changeset->getMetadata(), 'copy:lines', array());
    $highlight_old = $this->getHighlightOld();
    $highlight_new = $this->getHighlightNew();
    $old_render = $this->getOldRender();
    $new_render = $this->getNewRender();
    $original_left = $this->getOriginalOld();
    $original_right = $this->getOriginalNew();
    $depths = $this->getDepths();
    $mask = $this->getMask();

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

          $contents[] = javelin_tag(
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
              ? pht("Show First 20 Lines")
              : pht("\xE2\x96\xB2 Show 20 Lines"));
        }

        $contents[] = javelin_tag(
          'a',
          array(
            'href' => '#',
            'mustcapture' => true,
            'sigil'       => 'show-more',
            'meta'        => array(
              'type'   => 'all',
              'ref'    => $reference,
              'range'  => "{$top}-{$len}/{$top}-{$len}",
            ),
          ),
          pht('Show All %d Lines', $len));

        $is_last_block = false;
        if ($ii + $len >= $rows) {
          $is_last_block = true;
        }

        if ($len > 40) {
          $contents[] = javelin_tag(
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
              ? pht("Show Last 20 Lines")
              : pht("\xE2\x96\xBC Show 20 Lines"));
        }

        $context = null;
        $context_line = null;
        if (!$is_last_block && $depths[$ii + $len]) {
          for ($l = $ii + $len - 1; $l >= $ii; $l--) {
            $line = $new_lines[$l]['text'];
            if ($depths[$l] < $depths[$ii + $len] && trim($line) != '') {
              $context = $new_render[$l];
              $context_line = $new_lines[$l]['line'];
              break;
            }
          }
        }

        $container = javelin_tag(
          'tr',
          array(
            'sigil' => 'context-target',
          ),
          array(
            phutil_tag(
              'td',
              array(
                'colspan' => 2,
                'class' => 'show-more',
              ),
              phutil_implode_html(
                " \xE2\x80\xA2 ", // Bullet
                $contents)),
            phutil_tag(
              'th',
              array(
                'class' => 'show-context-line',
              ),
              $context_line ? (int)$context_line : null),
            phutil_tag(
              'td',
              array(
                'colspan' => 3,
                'class' => 'show-context',
              ),
              // TODO: [HTML] Escaping model here isn't ideal.
              phutil_safe_html($context)),
          ));

        $html[] = $container;

        $ii += ($len - 1);
        continue;
      }

      $o_num = null;
      $o_classes = 'left';
      $o_text = null;
      if (isset($old_lines[$ii])) {
        $o_num  = $old_lines[$ii]['line'];
        $o_text = isset($old_render[$ii]) ? $old_render[$ii] : null;
        if ($old_lines[$ii]['type']) {
          if ($old_lines[$ii]['type'] == '\\') {
            $o_text = $old_lines[$ii]['text'];
            $o_classes .= ' comment';
          } else if ($original_left && !isset($highlight_old[$o_num])) {
            $o_classes .= ' old-rebase';
          } else if (empty($new_lines[$ii])) {
            $o_classes .= ' old old-full';
          } else {
            $o_classes .= ' old';
          }
        }
      }

      $n_copy = hsprintf('<td class="copy" />');
      $n_cov = null;
      $n_colspan = 2;
      $n_classes = '';
      $n_num  = null;
      $n_text = null;

      if (isset($new_lines[$ii])) {
        $n_num  = $new_lines[$ii]['line'];
        $n_text = isset($new_render[$ii]) ? $new_render[$ii] : null;
        $coverage = $this->getCodeCoverage();

        if ($coverage !== null) {
          if (empty($coverage[$n_num - 1])) {
            $cov_class = 'N';
          } else {
            $cov_class = $coverage[$n_num - 1];
          }
          $cov_class = 'cov-'.$cov_class;
          $n_cov = hsprintf('<td class="cov %s"></td>', $cov_class);
          $n_colspan--;
        }

        if ($new_lines[$ii]['type']) {
          if ($new_lines[$ii]['type'] == '\\') {
            $n_text = $new_lines[$ii]['text'];
            $n_class = 'comment';
          } else if ($original_right && !isset($highlight_new[$n_num])) {
            $n_class = 'new-rebase';
          } else if (empty($old_lines[$ii])) {
            $n_class = 'new new-full';
          } else {
            $n_class = 'new';
          }
          $n_classes = $n_class;

          if ($new_lines[$ii]['type'] == '\\' || !isset($copy_lines[$n_num])) {
            $n_copy = hsprintf('<td class="copy %s"></td>', $n_class);
          } else {
            list($orig_file, $orig_line, $orig_type) = $copy_lines[$n_num];
            $title = ($orig_type == '-' ? 'Moved' : 'Copied').' from ';
            if ($orig_file == '') {
              $title .= "line {$orig_line}";
            } else {
              $title .=
                basename($orig_file).
                ":{$orig_line} in dir ".
                dirname('/'.$orig_file);
            }
            $class = ($orig_type == '-' ? 'new-move' : 'new-copy');
            $n_copy = javelin_tag(
              'td',
              array(
                'meta' => array(
                  'msg' => $title,
                ),
                'class' => 'copy '.$class,
              ),
              '');
          }
        }
      }
      $n_classes .= ' right'.$n_colspan;

      if (isset($hunk_starts[$o_num])) {
        $html[] = $context_not_available;
      }

      if ($o_num && $left_id) {
        $o_id = 'C'.$left_id.$left_char.'L'.$o_num;
      } else {
        $o_id = null;
      }

      if ($n_num && $right_id) {
        $n_id = 'C'.$right_id.$right_char.'L'.$n_num;
      } else {
        $n_id = null;
      }

      // NOTE: The Javascript is sensitive to whitespace changes in this
      // block!

      $html[] = hsprintf(
        '<tr>'.
          '%s'.
          '<td class="%s">%s</td>'.
          '%s'.
          '%s'.
          // NOTE: This is a unicode zero-width space, which we use as a hint
          // when intercepting 'copy' events to make sure sensible text ends
          // up on the clipboard. See the 'phabricator-oncopy' behavior.
          '<td class="%s" colspan="%s">'.
            "\xE2\x80\x8B%s".
          '</td>'.
          '%s'.
        '</tr>',
        phutil_tag('th', array('id' => $o_id), $o_num),
        $o_classes, $o_text,
        phutil_tag('th', array('id' => $n_id), $n_num),
        $n_copy,
        $n_classes, $n_colspan, $n_text,
        $n_cov);

      if ($context_not_available && ($ii == $rows - 1)) {
        $html[] = $context_not_available;
      }

      $old_comments = $this->getOldComments();
      $new_comments = $this->getNewComments();

      if ($o_num && isset($old_comments[$o_num])) {
        foreach ($old_comments[$o_num] as $comment) {
          $comment_html = $this->renderInlineComment($comment,
                                                     $on_right = false);
          $new = '';
          if ($n_num && isset($new_comments[$n_num])) {
            foreach ($new_comments[$n_num] as $key => $new_comment) {
              if ($comment->isCompatible($new_comment)) {
                $new = $this->renderInlineComment($new_comment,
                                                  $on_right = true);
                unset($new_comments[$n_num][$key]);
              }
            }
          }
          $html[] = hsprintf(
            '<tr class="inline">'.
              '<th />'.
              '<td class="left">%s</td>'.
              '<th />'.
              '<td colspan="3" class="right3">%s</td>'.
            '</tr>',
            $comment_html,
            $new);
        }
      }
      if ($n_num && isset($new_comments[$n_num])) {
        foreach ($new_comments[$n_num] as $comment) {
          $comment_html = $this->renderInlineComment($comment,
                                                     $on_right = true);
          $html[] = hsprintf(
            '<tr class="inline">'.
              '<th />'.
              '<td class="left" />'.
              '<th />'.
              '<td colspan="3" class="right3">%s</td>'.
            '</tr>',
            $comment_html);
        }
      }
    }

    return $this->wrapChangeInTable(phutil_implode_html('', $html));
  }

  public function renderFileChange($old_file = null,
                                   $new_file = null,
                                   $id = 0,
                                   $vs = 0) {
    $old = null;
    if ($old_file) {
      $old = phutil_tag(
        'div',
        array(
          'class' => 'differential-image-stage'
        ),
        phutil_tag(
          'img',
          array(
            'src' => $old_file->getBestURI(),
          )));
    }

    $new = null;
    if ($new_file) {
      $new = phutil_tag(
        'div',
        array(
          'class' => 'differential-image-stage'
        ),
        phutil_tag(
          'img',
          array(
            'src' => $new_file->getBestURI(),
          )));
    }

    $html_old = array();
    $html_new = array();
    foreach ($this->getOldComments() as $on_line => $comment_group) {
      foreach ($comment_group as $comment) {
        $comment_html = $this->renderInlineComment($comment, $on_right = false);
        $html_old[] = hsprintf(
          '<tr class="inline">'.
          '<th />'.
          '<td class="left">%s</td>'.
          '<th />'.
          '<td class="right3" colspan="3" />'.
          '</tr>',
          $comment_html);
      }
    }
    foreach ($this->getNewComments() as $lin_line => $comment_group) {
      foreach ($comment_group as $comment) {
        $comment_html = $this->renderInlineComment($comment, $on_right = true);
        $html_new[] = hsprintf(
          '<tr class="inline">'.
          '<th />'.
          '<td class="left" />'.
          '<th />'.
          '<td class="right3" colspan="3">%s</td>'.
          '</tr>',
          $comment_html);
      }
    }

    if (!$old) {
      $th_old = hsprintf('<th></th>');
    } else {
      $th_old = hsprintf('<th id="C%sOL1">1</th>', $vs);
    }

    if (!$new) {
      $th_new = hsprintf('<th></th>');
    } else {
      $th_new = hsprintf('<th id="C%sNL1">1</th>', $id);
    }

    $output = hsprintf(
      '<tr class="differential-image-diff">'.
        '%s'.
        '<td class="left differential-old-image">%s</td>'.
        '%s'.
        '<td class="right3 differential-new-image" colspan="3">%s</td>'.
      '</tr>'.
      '%s'.
      '%s',
      $th_old,
      $old,
      $th_new,
      $new,
      phutil_implode_html('', $html_old),
      phutil_implode_html('', $html_new));

    $output = $this->wrapChangeInTable($output);

    return $this->renderChangesetTable($output);
  }

}
