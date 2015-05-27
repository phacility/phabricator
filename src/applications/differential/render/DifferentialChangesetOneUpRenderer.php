<?php

final class DifferentialChangesetOneUpRenderer
  extends DifferentialChangesetHTMLRenderer {

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

    $no_copy = phutil_tag('td', array('class' => 'copy'));
    $no_coverage = null;

    $column_width = 4;

    $hidden = new PHUIDiffRevealIconView();

    $out = array();
    foreach ($primitives as $k => $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
        case 'old-file':
        case 'new-file':
          $is_old = ($type == 'old' || $type == 'old-file');

          $o_hidden = array();
          $n_hidden = array();

          for ($look = $k + 1; isset($primitives[$look]); $look++) {
            $next = $primitives[$look];
            switch ($next['type']) {
              case 'inline':
                $comment = $next['comment'];
                if ($comment->isHidden()) {
                  if ($next['right']) {
                    $n_hidden[] = $comment;
                  } else {
                    $o_hidden[] = $comment;
                  }
                }
                break;
              default:
                break 2;
            }
          }

          $cells = array();
          if ($is_old) {
            if ($p['htype']) {
              $class = 'left old';
            } else {
              $class = 'left';
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
            if ($o_hidden) {
              $line = array($hidden, $line);
            }

            $cells[] = phutil_tag('th', array('id' => $left_id), $line);

            $cells[] = phutil_tag('th', array());
            $cells[] = $no_copy;
            $cells[] = phutil_tag('td', array('class' => $class), $p['render']);
            $cells[] = $no_coverage;
          } else {
            if ($p['htype']) {
              $class = 'right new';
              $cells[] = phutil_tag('th', array());
            } else {
              $class = 'right';
              if ($left_prefix) {
                $left_id = $left_prefix.$p['oline'];
              } else {
                $left_id = null;
              }

              $oline = $p['oline'];
              if ($o_hidden) {
                $oline = array($hidden, $oline);
              }

              $cells[] = phutil_tag('th', array('id' => $left_id), $oline);
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
            if ($n_hidden) {
              $line = array($hidden, $line);
            }

            $cells[] = phutil_tag('th', array('id' => $right_id), $line);

            $cells[] = $no_copy;
            $cells[] = phutil_tag('td', array('class' => $class), $p['render']);
            $cells[] = $no_coverage;
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

    if ($out) {
      return $this->wrapChangeInTable(phutil_implode_html('', $out));
    }

    return null;
  }

  public function renderFileChange(
    $old_file = null,
    $new_file = null,
    $id = 0,
    $vs = 0) {

    // TODO: This should eventually merge into the normal primitives pathway,
    // but fake it for now and just share as much code as possible.

    $primitives = array();
    if ($old_file) {
      $primitives[] = array(
        'type' => 'old-file',
        'htype' => ($new_file ? 'new-file' : null),
        'file' => $old_file,
        'line' => 1,
        'render' => $this->renderImageStage($old_file),
      );
    }

    if ($new_file) {
      $primitives[] = array(
        'type' => 'new-file',
        'htype' => ($old_file ? 'old-file' : null),
        'file' => $new_file,
        'line' => 1,
        'oline' => ($old_file ? 1 : null),
        'render' => $this->renderImageStage($old_file),
      );
    }

    // TODO: We'd like to share primitive code here, but buildPrimitives()
    // currently chokes on changesets with no textual data.
    foreach ($this->getOldComments() as $line => $group) {
      foreach ($group as $comment) {
        $primitives[] = array(
          'type' => 'inline',
          'comment' => $comment,
          'right' => false,
        );
      }
    }

    foreach ($this->getNewComments() as $line => $group) {
      foreach ($group as $comment) {
        $primitives[] = array(
          'type' => 'inline',
          'comment' => $comment,
          'right' => true,
        );
      }
    }

    $output = $this->renderPrimitives($primitives, 1);
    return $this->renderChangesetTable($output);
  }

  public function getRowScaffoldForInline(PHUIDiffInlineCommentView $view) {
    return id(new PHUIDiffOneUpInlineCommentRowScaffold())
      ->addInlineView($view);
  }

}
