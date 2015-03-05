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
      phutil_tag('col', array('class' => 'unified')),
    ));
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $primitives = $this->buildPrimitives($range_start, $range_len);

    list($left_prefix, $right_prefix) = $this->getLineIDPrefixes();

    $out = array();
    foreach ($primitives as $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
          $out[] = hsprintf('<tr>');
          if ($type == 'old') {
            if ($p['htype']) {
              $class = 'left old';
            } else {
              $class = 'left';
            }

            if ($left_prefix) {
              $left_id = $left_prefix.$p['line'];
            } else {
              $left_id = null;
            }
            $out[] = phutil_tag('th', array('id' => $left_id), $p['line']);

            $out[] = phutil_tag('th', array());
            $out[] = phutil_tag('td', array('class' => $class), $p['render']);
          } else {
            if ($p['htype']) {
              $class = 'right new';
              $out[] = phutil_tag('th', array());
            } else {
              $class = 'right';
              if ($left_prefix) {
                $left_id = $left_prefix.$p['oline'];
              } else {
                $left_id = null;
              }
              $out[] = phutil_tag('th', array('id' => $left_id), $p['oline']);
            }

            if ($right_prefix) {
              $right_id = $right_prefix.$p['line'];
            } else {
              $right_id = null;
            }
            $out[] = phutil_tag('th', array('id' => $right_id), $p['line']);

            $out[] = phutil_tag('td', array('class' => $class), $p['render']);
          }
          $out[] = hsprintf('</tr>');
          break;
        case 'inline':
          $out[] = hsprintf('<tr><th /><th />');
          $out[] = hsprintf('<td>');

          $inline = $this->buildInlineComment(
            $p['comment'],
            $p['right']);
          $inline->setBuildScaffolding(false);
          $out[] = $inline->render();

          $out[] = hsprintf('</td></tr>');
          break;
        case 'no-context':
          $out[] = hsprintf(
            '<tr><td class="show-more" colspan="3">%s</td></tr>',
            pht('Context not available.'));
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
                'colspan' => 3,
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

    throw new PhutilMethodNotImplementedException();
  }

}
