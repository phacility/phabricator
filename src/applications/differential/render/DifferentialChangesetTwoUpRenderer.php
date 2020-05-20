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
            if (isset($depth_only[$ii])) {
              if ($depth_only[$ii] == '>') {
                // When a line has depth-only change, we only highlight the
                // left side of the diff if the depth is decreasing. When the
                // depth is increasing, the ">>" marker on the right hand side
                // of the diff generally provides enough visibility on its own.

                $o_class = '';
              } else {
                $o_class = 'old';
              }
            } else {
              $o_class = 'old';
            }
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
            // When a line has a depth-only change, never highlight it on
            // the right side. The ">>" marker generally provides enough
            // visibility on its own for indent depth increases, and the left
            // side is still highlighted for indent depth decreases.

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

  public function renderDocumentEngineBlocks(
    PhabricatorDocumentEngineBlocks $block_list,
    $old_changeset_key,
    $new_changeset_key) {

    $engine = $this->getDocumentEngine();

    $old_ref = null;
    $new_ref = null;
    $refs = $block_list->getDocumentRefs();
    if ($refs) {
      list($old_ref, $new_ref) = $refs;
    }

    $old_comments = $this->getOldComments();
    $new_comments = $this->getNewComments();

    $rows = array();
    $gap = array();
    $in_gap = false;

    // NOTE: The generated layout is affected by range constraints, and may
    // represent only a slice of the document.

    $layout = $block_list->newTwoUpLayout();
    $available_count = $block_list->getLayoutAvailableRowCount();

    foreach ($layout as $idx => $row) {
      list($old, $new) = $row;

      if ($old) {
        $old_key = $old->getBlockKey();
        $is_visible = $old->getIsVisible();
      } else {
        $old_key = null;
      }

      if ($new) {
        $new_key = $new->getBlockKey();
        $is_visible = $new->getIsVisible();
      } else {
        $new_key = null;
      }

      if (!$is_visible) {
        if (!$in_gap) {
          $in_gap = true;
        }
        $gap[$idx] = $row;
        continue;
      }

      if ($in_gap) {
        $in_gap = false;
        $rows[] = $this->renderDocumentEngineGap(
          $gap,
          $available_count);
        $gap = array();
      }

      if ($old) {
        $is_rem = ($old->getDifferenceType() === '-');
      } else {
        $is_rem = false;
      }

      if ($new) {
        $is_add = ($new->getDifferenceType() === '+');
      } else {
        $is_add = false;
      }

      if ($is_rem && $is_add) {
        $block_diff = $engine->newBlockDiffViews(
          $old_ref,
          $old,
          $new_ref,
          $new);

        $old_content = $block_diff->getOldContent();
        $new_content = $block_diff->getNewContent();

        $old_classes = $block_diff->getOldClasses();
        $new_classes = $block_diff->getNewClasses();
      } else {
        $old_classes = array();
        $new_classes = array();

        if ($old) {
          $old_content = $engine->newBlockContentView(
            $old_ref,
            $old);

          if ($is_rem) {
            $old_classes[] = 'old';
            $old_classes[] = 'old-full';
          }
        } else {
          $old_content = null;
        }

        if ($new) {
          $new_content = $engine->newBlockContentView(
            $new_ref,
            $new);

          if ($is_add) {
            $new_classes[] = 'new';
            $new_classes[] = 'new-full';
          }
        } else {
          $new_content = null;
        }
      }

      $old_classes[] = 'diff-flush';
      $old_classes = implode(' ', $old_classes);

      $new_classes[] = 'diff-flush';
      $new_classes = implode(' ', $new_classes);

      $old_inline_rows = array();
      if ($old_key !== null) {
        $old_inlines = idx($old_comments, $old_key, array());
        foreach ($old_inlines as $inline) {
          $inline = $this->buildInlineComment(
            $inline,
            $on_right = false);
          $old_inline_rows[] = $this->getRowScaffoldForInline($inline);
        }
      }

      $new_inline_rows = array();
      if ($new_key !== null) {
        $new_inlines = idx($new_comments, $new_key, array());
        foreach ($new_inlines as $inline) {
          $inline = $this->buildInlineComment(
            $inline,
            $on_right = true);
          $new_inline_rows[] = $this->getRowScaffoldForInline($inline);
        }
      }

      if ($old_content === null) {
        $old_id = null;
      } else {
        $old_id = "C{$old_changeset_key}OL{$old_key}";
      }

      $old_line_cell = phutil_tag(
        'td',
        array(
          'id' => $old_id,
          'data-n' => $old_key,
          'class' => 'n',
        ));

      $old_content_cell = phutil_tag(
        'td',
        array(
          'class' => $old_classes,
          'data-copy-mode' => 'copy-l',
        ),
        $old_content);

      if ($new_content === null) {
        $new_id = null;
      } else {
        $new_id = "C{$new_changeset_key}NL{$new_key}";
      }

      $new_line_cell = phutil_tag(
        'td',
        array(
          'id' => $new_id,
          'data-n' => $new_key,
          'class' => 'n',
        ));

      $copy_gutter = phutil_tag(
        'td',
        array(
          'class' => 'copy',
        ));

      $new_content_cell = phutil_tag(
        'td',
        array(
          'class' => $new_classes,
          'colspan' => '2',
          'data-copy-mode' => 'copy-r',
        ),
        $new_content);

      $row_view = phutil_tag(
        'tr',
        array(),
        array(
          $old_line_cell,
          $old_content_cell,
          $new_line_cell,
          $copy_gutter,
          $new_content_cell,
        ));

      $rows[] = array(
        $row_view,
        $old_inline_rows,
        $new_inline_rows,
      );
    }

    if ($in_gap) {
      $rows[] = $this->renderDocumentEngineGap(
        $gap,
        $available_count);
    }

    $output = $this->wrapChangeInTable($rows);

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
        if ($new_line === null) {
          continue;
        }

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

  private function renderDocumentEngineGap(array $gap, $available_count) {
    $content = $this->renderShowContextLinks(
      head_key($gap),
      count($gap),
      $available_count,
      $is_blocks = true);

    return javelin_tag(
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
        $content));
  }

}
