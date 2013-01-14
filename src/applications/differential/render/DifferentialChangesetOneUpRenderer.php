<?php

final class DifferentialChangesetOneUpRenderer
  extends DifferentialChangesetHTMLRenderer {

  public function isOneUpRenderer() {
    return true;
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $primitives = $this->buildPrimitives($range_start, $range_len);

    $out = array();
    foreach ($primitives as $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
          $out[] = '<tr>';
          if ($type == 'old') {
            if ($p['htype']) {
              $class = 'left old';
            } else {
              $class = 'left';
            }
            $out[] = '<th>'.$p['line'].'</th>';
            $out[] = '<th></th>';
            $out[] = '<td class="'.$class.'">'.$p['render'].'</td>';
          } else if ($type == 'new') {
            if ($p['htype']) {
              $class = 'right new';
              $out[] = '<th />';
            } else {
              $class = 'right';
              $out[] = '<th>'.$p['oline'].'</th>';
            }
            $out[] = '<th>'.$p['line'].'</th>';
            $out[] = '<td class="'.$class.'">'.$p['render'].'</td>';
          }
          $out[] = '</tr>';
          break;
        case 'inline':
          $out[] = '<tr><th /><th />';
          $out[] = '<td>';

          $inline = $this->buildInlineComment(
            $p['comment'],
            $p['right']);
          $inline->setBuildScaffolding(false);
          $out[] = $inline->render();

          $out[] = '</td></tr>';
          break;
        default:
          $out[] = '<tr><th /><th /><td>'.$type.'</td></tr>';
          break;
      }
    }

    if ($out) {
      return $this->wrapChangeInTable(implode('', $out));
    }
    return null;
  }

  public function renderFileChange($old_file = null,
                                   $new_file = null,
                                   $id = 0,
                                   $vs = 0) {
    throw new Exception("Not implemented!");
  }

}
