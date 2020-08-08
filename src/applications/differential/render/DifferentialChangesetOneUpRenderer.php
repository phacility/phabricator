<?php

final class DifferentialChangesetOneUpRenderer
  extends DifferentialChangesetHTMLRenderer {

  private $simpleMode;

  public function setSimpleMode($simple_mode) {
    $this->simpleMode = $simple_mode;
    return $this;
  }

  public function getSimpleMode() {
    return $this->simpleMode;
  }

  public function isOneUpRenderer() {
    return true;
  }

  protected function getRendererTableClass() {
    return 'diff-1up';
  }

  public function getRendererKey() {
    return '1up';
  }

  protected function renderColgroup() {
    return phutil_tag('colgroup', array(), array(
      phutil_tag('col', array('class' => 'num')),
      phutil_tag('col', array('class' => 'num')),
      phutil_tag('col', array('class' => 'copy')),
      phutil_tag('col', array('class' => 'unified')),
    ));
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $primitives = $this->buildPrimitives($range_start, $range_len);
    return $this->renderPrimitives($primitives, $rows);
  }

  protected function renderPrimitives(array $primitives, $rows) {
    list($left_prefix, $right_prefix) = $this->getLineIDPrefixes();

    $is_simple = $this->getSimpleMode();

    $no_copy = phutil_tag('td', array('class' => 'copy'));
    $no_coverage = null;

    $column_width = 4;

    $aural_minus = javelin_tag(
      'span',
      array(
        'aural' => true,
        'data-aural' => true,
      ),
      '- ');

    $aural_plus = javelin_tag(
      'span',
      array(
        'aural' => true,
        'data-aural' => true,
      ),
      '+ ');

    $out = array();
    foreach ($primitives as $k => $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
        case 'old-file':
        case 'new-file':
          $is_old = ($type == 'old' || $type == 'old-file');

          $cells = array();
          if ($is_old) {
            if ($p['htype']) {
              if ($p['htype'] === '\\') {
                $class = 'comment';
              } else if (empty($p['oline'])) {
                $class = 'left old old-full';
              } else {
                $class = 'left old';
              }
              $aural = $aural_minus;
            } else {
              $class = 'left';
              $aural = null;
            }

            if ($type == 'old-file') {
              $class = "{$class} differential-old-image";
            }

            if ($left_prefix) {
              $left_id = $left_prefix.$p['line'];
            } else {
              $left_id = null;
            }

            $line = $p['line'];

            $cells[] = phutil_tag(
              'td',
              array(
                'id' => $left_id,
                'class' => $class.' n',
                'data-n' => $line,
              ));

            $render = $p['render'];
            if ($aural !== null) {
              $render = array($aural, $render);
            }

            $cells[] = phutil_tag(
              'td',
              array(
                'class' => $class.' n',
              ));
            $cells[] = $no_copy;
            $cells[] = phutil_tag('td', array('class' => $class), $render);
            $cells[] = $no_coverage;
          } else {
            if ($p['htype']) {
              if ($p['htype'] === '\\') {
                $class = 'comment';
              } else if (empty($p['oline'])) {
                $class = 'right new new-full';
              } else {
                $class = 'right new';
              }
              $cells[] = phutil_tag(
                'td',
                array(
                  'class' => $class.' n',
                ));
              $aural = $aural_plus;
            } else {
              $class = 'right';
              if ($left_prefix) {
                $left_id = $left_prefix.$p['oline'];
              } else {
                $left_id = null;
              }

              $oline = $p['oline'];

              $cells[] = phutil_tag(
                'td',
                array(
                  'id' => $left_id,
                  'class' => 'n',
                  'data-n' => $oline,
                ));
              $aural = null;
            }

            if ($type == 'new-file') {
              $class = "{$class} differential-new-image";
            }

            if ($right_prefix) {
              $right_id = $right_prefix.$p['line'];
            } else {
              $right_id = null;
            }

            $line = $p['line'];

            $cells[] = phutil_tag(
              'td',
              array(
                'id' => $right_id,
                'class' => $class.' n',
                'data-n' => $line,
              ));

            $render = $p['render'];
            if ($aural !== null) {
              $render = array($aural, $render);
            }

            $cells[] = $no_copy;

            $cells[] = phutil_tag(
              'td',
              array(
                'class' => $class,
                'data-copy-mode' => 'copy-unified',
              ),
              $render);

            $cells[] = $no_coverage;
          }

          // In simple mode, only render the text. This is used to render
          // "Edit Suggestions" in inline comments.
          if ($is_simple) {
            $cells = array($cells[3]);
          }

          $out[] = phutil_tag('tr', array(), $cells);

          break;
        case 'inline':
          $inline = $this->buildInlineComment(
            $p['comment'],
            $p['right']);
          $out[] = $this->getRowScaffoldForInline($inline);
          break;
        case 'no-context':
          $out[] = phutil_tag(
            'tr',
            array(),
            phutil_tag(
              'td',
              array(
                'class' => 'show-more',
                'colspan' => $column_width,
              ),
              pht('Context not available.')));
          break;
        case 'context':
          $top = $p['top'];
          $len = $p['len'];

          $links = $this->renderShowContextLinks($top, $len, $rows);

          $out[] = javelin_tag(
            'tr',
            array(
              'sigil' => 'context-target',
            ),
            phutil_tag(
              'td',
              array(
                'class' => 'show-more',
                'colspan' => $column_width,
              ),
              $links));
          break;
        default:
          $out[] = hsprintf('<tr><th /><th /><td>%s</td></tr>', $type);
          break;
      }
    }

    $result = null;

    if ($out) {
      if ($is_simple) {
        $result = $this->newSimpleTable($out);
      } else {
        $result = $this->wrapChangeInTable(phutil_implode_html('', $out));
      }
    }

    return $result;
  }

  public function renderDocumentEngineBlocks(
    PhabricatorDocumentEngineBlocks $block_list,
    $old_changeset_key,
    $new_changeset_key) {

    $engine = $this->getDocumentEngine();
    $layout = $block_list->newTwoUpLayout();

    $old_comments = $this->getOldComments();
    $new_comments = $this->getNewComments();

    $unchanged = array();
    foreach ($layout as $key => $row) {
      list($old, $new) = $row;

      if (!$old) {
        continue;
      }

      if (!$new) {
        continue;
      }

      if ($old->getDifferenceType() !== null) {
        continue;
      }

      if ($new->getDifferenceType() !== null) {
        continue;
      }

      $unchanged[$key] = true;
    }

    $rows = array();
    $count = count($layout);
    for ($ii = 0; $ii < $count;) {
      $start = $ii;

      for ($jj = $ii; $jj < $count; $jj++) {
        list($old, $new) = $layout[$jj];

        if (empty($unchanged[$jj])) {
          break;
        }

        $rows[] = array(
          'type' => 'unchanged',
          'layoutKey' => $jj,
        );
      }
      $ii = $jj;

      for ($jj = $ii; $jj < $count; $jj++) {
        list($old, $new) = $layout[$jj];

        if (!empty($unchanged[$jj])) {
          break;
        }

        $rows[] = array(
          'type' => 'old',
          'layoutKey' => $jj,
        );
      }

      for ($jj = $ii; $jj < $count; $jj++) {
        list($old, $new) = $layout[$jj];

        if (!empty($unchanged[$jj])) {
          break;
        }

        $rows[] = array(
          'type' => 'new',
          'layoutKey' => $jj,
        );
      }
      $ii = $jj;

      // We always expect to consume at least one row when iterating through
      // the loop and make progress. If we don't, bail out to avoid spinning
      // to death.
      if ($ii === $start) {
        throw new Exception(
          pht(
            'Failed to make progress during 1up diff layout.'));
      }
    }

    $old_ref = null;
    $new_ref = null;
    $refs = $block_list->getDocumentRefs();
    if ($refs) {
      list($old_ref, $new_ref) = $refs;
    }

    $view = array();
    foreach ($rows as $row) {
      $row_type = $row['type'];
      $layout_key = $row['layoutKey'];
      $row_layout = $layout[$layout_key];
      list($old, $new) = $row_layout;

      if ($old) {
        $old_key = $old->getBlockKey();
      } else {
        $old_key = null;
      }

      if ($new) {
        $new_key = $new->getBlockKey();
      } else {
        $new_key = null;
      }

      $cells = array();
      $cell_classes = array();

      if ($row_type === 'unchanged') {
        $cell_content = $engine->newBlockContentView(
          $old_ref,
          $old);
      } else if ($old && $new) {
        $block_diff = $engine->newBlockDiffViews(
          $old_ref,
          $old,
          $new_ref,
          $new);

        // TODO: We're currently double-rendering this: once when building
        // the old row, and once when building the new one. In both cases,
        // we throw away the other half of the output. We could cache this
        // to improve performance.

        if ($row_type === 'old') {
          $cell_content = $block_diff->getOldContent();
          $cell_classes = $block_diff->getOldClasses();
        } else {
          $cell_content = $block_diff->getNewContent();
          $cell_classes = $block_diff->getNewClasses();
        }
      } else if ($row_type === 'old') {
        if (!$old_ref || !$old) {
          continue;
        }

        $cell_content = $engine->newBlockContentView(
          $old_ref,
          $old);

        $cell_classes[] = 'old';
        $cell_classes[] = 'old-full';

        $new_key = null;
      } else if ($row_type === 'new') {
        if (!$new_ref || !$new) {
          continue;
        }

        $cell_content = $engine->newBlockContentView(
          $new_ref,
          $new);

        $cell_classes[] = 'new';
        $cell_classes[] = 'new-full';

        $old_key = null;
      }

      if ($old_key === null) {
        $old_id = null;
      } else {
        $old_id = "C{$old_changeset_key}OL{$old_key}";
      }

      if ($new_key === null) {
        $new_id = null;
      } else {
        $new_id = "C{$new_changeset_key}NL{$new_key}";
      }

      $cells[] = phutil_tag(
        'td',
        array(
          'id' => $old_id,
          'data-n' => $old_key,
          'class' => 'n',
        ));

      $cells[] = phutil_tag(
        'td',
        array(
          'id' => $new_id,
          'data-n' => $new_key,
          'class' => 'n',
        ));

      $cells[] = phutil_tag(
        'td',
        array(
          'class' => 'copy',
        ));

      $cell_classes[] = 'diff-flush';
      $cell_classes = implode(' ', $cell_classes);

      $cells[] = phutil_tag(
        'td',
        array(
          'class' => $cell_classes,
          'data-copy-mode' => 'copy-unified',
        ),
        $cell_content);

      $view[] = phutil_tag(
        'tr',
        array(),
        $cells);

      if ($old_key !== null) {
        $old_inlines = idx($old_comments, $old_key, array());
        foreach ($old_inlines as $inline) {
          $inline = $this->buildInlineComment(
            $inline,
            $on_right = false);
          $view[] = $this->getRowScaffoldForInline($inline);
        }
      }

      if ($new_key !== null) {
        $new_inlines = idx($new_comments, $new_key, array());
        foreach ($new_inlines as $inline) {
          $inline = $this->buildInlineComment(
            $inline,
            $on_right = true);
          $view[] = $this->getRowScaffoldForInline($inline);
        }
      }
    }

    $output = $this->wrapChangeInTable($view);
    return $this->renderChangesetTable($output);
  }

  public function getRowScaffoldForInline(PHUIDiffInlineCommentView $view) {
    return id(new PHUIDiffOneUpInlineCommentRowScaffold())
      ->addInlineView($view);
  }


  private function newSimpleTable($content) {
    return phutil_tag(
      'table',
      array(
        'class' => 'diff-1up-simple-table',
      ),
      $content);
  }

}
