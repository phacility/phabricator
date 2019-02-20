<?php

final class DifferentialChangesetTwoUpRenderer
  extends DifferentialChangesetHTMLRenderer {

  private $newOffsetMap;

  public function isOneUpRenderer() {
    return false;
  }

  protected function getRendererTableClass() {
    return 'diff-2up';
  }

  public function getRendererKey() {
    return '2up';
  }

  protected function renderColgroup() {
    return phutil_tag('colgroup', array(), array(
      phutil_tag('col', array('class' => 'num')),
      phutil_tag('col', array('class' => 'left')),
      phutil_tag('col', array('class' => 'num')),
      phutil_tag('col', array('class' => 'copy')),
      phutil_tag('col', array('class' => 'right')),
      phutil_tag('col', array('class' => 'cov')),
    ));
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
            'class' => 'show-more',
          ),
          pht('Context not available.')));
    }

    $html = array();

    $old_lines = $this->getOldLines();
    $new_lines = $this->getNewLines();
    $gaps = $this->getGaps();
    $reference = $this->getRenderingReference();

    list($left_prefix, $right_prefix) = $this->getLineIDPrefixes();

    $changeset = $this->getChangeset();
    $copy_lines = idx($changeset->getMetadata(), 'copy:lines', array());
    $highlight_old = $this->getHighlightOld();
    $highlight_new = $this->getHighlightNew();
    $old_render = $this->getOldRender();
    $new_render = $this->getNewRender();
    $original_left = $this->getOriginalOld();
    $original_right = $this->getOriginalNew();
    $mask = $this->getMask();

    $scope_engine = $this->getScopeEngine();
    $offset_map = null;
    $depth_only = $this->getDepthOnlyLines();

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

        $contents = $this->renderShowContextLinks($top, $len, $rows);

        $is_last_block = false;
        if ($ii + $len >= $rows) {
          $is_last_block = true;
        }

        $context_text = null;
        $context_line = null;
        if (!$is_last_block && $scope_engine) {
          $target_line = $new_lines[$ii + $len]['line'];
          $context_line = $scope_engine->getScopeStart($target_line);
          if ($context_line !== null) {
            // The scope engine returns a line number in the file. We need
            // to map that back to a display offset in the diff.
            if (!$offset_map) {
              $offset_map = $this->getNewLineToOffsetMap();
            }
            $offset = $offset_map[$context_line];
            $context_text = $new_render[$offset];
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
                'class' => 'show-context-line n left-context',
              )),
            phutil_tag(
              'td',
              array(
                'class' => 'show-more',
              ),
              $contents),
            phutil_tag(
              'td',
              array(
                'class' => 'show-context-line n',
                'data-n' => $context_line,
              )),
            phutil_tag(
              'td',
              array(
                'colspan' => 3,
                'class' => 'show-context',
              ),
              // TODO: [HTML] Escaping model here isn't ideal.
              phutil_safe_html($context_text)),
          ));

        $html[] = $container;

        $ii += ($len - 1);
        continue;
      }

      $o_num = null;
      $o_classes = '';
      $o_text = null;
      if (isset($old_lines[$ii])) {
        $o_num  = $old_lines[$ii]['line'];
        $o_text = isset($old_render[$ii]) ? $old_render[$ii] : null;
        if ($old_lines[$ii]['type']) {
          if ($old_lines[$ii]['type'] == '\\') {
            $o_text = $old_lines[$ii]['text'];
            $o_class = 'comment';
          } else if ($original_left && !isset($highlight_old[$o_num])) {
            $o_class = 'old-rebase';
          } else if (empty($new_lines[$ii])) {
            $o_class = 'old old-full';
          } else {
            $o_class = 'old';
          }
          $o_classes = $o_class;
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
          $n_cov = phutil_tag('td', array('class' => "cov {$cov_class}"));
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

            // NOTE: At least for the moment, I'm intentionally clearing the
            // line highlighting only on the right side of the diff when a
            // line has only depth changes. When a block depth is decreased,
            // this gives us a large color block on the left (to make it easy
            // to see the depth change) but a clean diff on the right (to make
            // it easy to pick out actual code changes).

            if (isset($depth_only[$ii])) {
              $n_class = '';
            } else {
              $n_class = 'new';
            }
          }
          $n_classes = $n_class;

          $not_copied =
            // If this line only changed depth, copy markers are pointless.
            (!isset($copy_lines[$n_num])) ||
            (isset($depth_only[$ii])) ||
            ($new_lines[$ii]['type'] == '\\');

          if ($not_copied) {
            $n_copy = phutil_tag('td', array('class' => 'copy'));
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
              ));
          }
        }
      }

      if (isset($hunk_starts[$o_num])) {
        $html[] = $context_not_available;
      }

      if ($o_num && $left_prefix) {
        $o_id = $left_prefix.$o_num;
      } else {
        $o_id = null;
      }

      if ($n_num && $right_prefix) {
        $n_id = $right_prefix.$n_num;
      } else {
        $n_id = null;
      }

      $old_comments = $this->getOldComments();
      $new_comments = $this->getNewComments();
      $scaffolds = array();

      if ($o_num && isset($old_comments[$o_num])) {
        foreach ($old_comments[$o_num] as $comment) {
          $inline = $this->buildInlineComment(
            $comment,
            $on_right = false);
          $scaffold = $this->getRowScaffoldForInline($inline);

          if ($n_num && isset($new_comments[$n_num])) {
            foreach ($new_comments[$n_num] as $key => $new_comment) {
              if ($comment->isCompatible($new_comment)) {
                $companion = $this->buildInlineComment(
                  $new_comment,
                  $on_right = true);

                $scaffold->addInlineView($companion);
                unset($new_comments[$n_num][$key]);
                break;
              }
            }
          }


          $scaffolds[] = $scaffold;
        }
      }

      if ($n_num && isset($new_comments[$n_num])) {
        foreach ($new_comments[$n_num] as $comment) {
          $inline = $this->buildInlineComment(
            $comment,
            $on_right = true);

          $scaffolds[] = $this->getRowScaffoldForInline($inline);
        }
      }

      $old_number = phutil_tag(
        'td',
        array(
          'id' => $o_id,
          'class' => $o_classes.' n',
          'data-n' => $o_num,
        ));

      $new_number = phutil_tag(
        'td',
        array(
          'id' => $n_id,
          'class' => $n_classes.' n',
          'data-n' => $n_num,
        ));

      $html[] = phutil_tag('tr', array(), array(
        $old_number,
        phutil_tag(
          'td',
          array(
            'class' => $o_classes,
            'data-copy-mode' => 'copy-l',
          ),
          $o_text),
        $new_number,
        $n_copy,
        phutil_tag(
          'td',
          array(
            'class' => $n_classes,
            'colspan' => $n_colspan,
            'data-copy-mode' => 'copy-r',
          ),
          $n_text),
        $n_cov,
      ));

      if ($context_not_available && ($ii == $rows - 1)) {
        $html[] = $context_not_available;
      }

      foreach ($scaffolds as $scaffold) {
        $html[] = $scaffold;
      }
    }

    return $this->wrapChangeInTable(phutil_implode_html('', $html));
  }

  public function renderFileChange(
    $old_file = null,
    $new_file = null,
    $id = 0,
    $vs = 0) {

    $old = null;
    if ($old_file) {
      $old = $this->renderImageStage($old_file);
    }

    $new = null;
    if ($new_file) {
      $new = $this->renderImageStage($new_file);
    }

    // If we don't have an explicit "vs" changeset, it's the left side of the
    // "id" changeset.
    if (!$vs) {
      $vs = $id;
    }

    $html_old = array();
    $html_new = array();
    foreach ($this->getOldComments() as $on_line => $comment_group) {
      foreach ($comment_group as $comment) {
        $inline = $this->buildInlineComment(
          $comment,
          $on_right = false);
        $html_old[] = $this->getRowScaffoldForInline($inline);
      }
    }
    foreach ($this->getNewComments() as $lin_line => $comment_group) {
      foreach ($comment_group as $comment) {
        $inline = $this->buildInlineComment(
          $comment,
          $on_right = true);
        $html_new[] = $this->getRowScaffoldForInline($inline);
      }
    }

    if (!$old) {
      $th_old = phutil_tag('th', array());
    } else {
      $th_old = phutil_tag('th', array('id' => "C{$vs}OL1"), 1);
    }

    if (!$new) {
      $th_new = phutil_tag('th', array());
    } else {
      $th_new = phutil_tag('th', array('id' => "C{$id}NL1"), 1);
    }

    $output = hsprintf(
      '<tr class="differential-image-diff">'.
        '%s'.
        '<td class="differential-old-image">%s</td>'.
        '%s'.
        '<td class="differential-new-image" colspan="3">%s</td>'.
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

  public function getRowScaffoldForInline(PHUIDiffInlineCommentView $view) {
    return id(new PHUIDiffTwoUpInlineCommentRowScaffold())
      ->addInlineView($view);
  }

  private function getNewLineToOffsetMap() {
    if ($this->newOffsetMap === null) {
      $new = $this->getNewLines();

      $map = array();
      foreach ($new as $offset => $new_line) {
        if ($new_line['line'] === null) {
          continue;
        }
        $map[$new_line['line']] = $offset;
      }

      $this->newOffsetMap = $map;
    }

    return $this->newOffsetMap;
  }

  protected function getTableSigils() {
    return array(
      'intercept-copy',
    );
  }

}
