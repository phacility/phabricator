<?php

abstract class DifferentialChangesetTestRenderer
  extends DifferentialChangesetRenderer {

  protected function renderChangeTypeHeader($force) {
    $changeset = $this->getChangeset();

    $old = nonempty($changeset->getOldFile(), '-');
    $current = nonempty($changeset->getFilename(), '-');
    $away = nonempty(implode(', ', $changeset->getAwayPaths()), '-');

    $ctype = $changeset->getChangeType();
    $ftype = $changeset->getFileType();
    $force = ($force ? '(forced)' : '(unforced)');

    return "CTYPE {$ctype} {$ftype} {$force}\n".
           "{$old}\n".
           "{$current}\n".
           "{$away}\n";
  }

  protected function renderUndershieldHeader() {
    return null;
  }

  public function renderShield($message, $force = 'default') {
    return "SHIELD ({$force}) {$message}\n";
  }

  protected function renderPropertyChangeHeader() {
    $changeset = $this->getChangeset();
    list($old, $new) = $this->getChangesetProperties($changeset);

    foreach (array_keys($old) as $key) {
      if ($old[$key] === idx($new, $key)) {
        unset($old[$key]);
        unset($new[$key]);
      }
    }

    if (!$old && !$new) {
      return null;
    }

    $props = '';
    foreach ($old as $key => $value) {
      $props .= "P - {$key} {$value}~\n";
    }
    foreach ($new as $key => $value) {
      $props .= "P + {$key} {$value}~\n";
    }

    return "PROPERTIES\n".$props;
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $out = array();

    $any_old = false;
    $any_new = false;
    $primitives = $this->buildPrimitives($range_start, $range_len);
    foreach ($primitives as $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
          if ($type == 'old') {
            $any_old = true;
          }
          if ($type == 'new') {
            $any_new = true;
          }
          $num = nonempty($p['line'], '-');
          $render = $p['render'];
          $htype = nonempty($p['htype'], '.');

          // TODO: This should probably happen earlier, whenever we deal with
          // \r and \t normalization?
          $render = str_replace(
            array(
              "\r",
              "\n",
            ),
            array(
              '\\r',
              '\\n',
            ),
            $render);

          $render = str_replace(
            array(
              '<span class="bright">',
              '</span>',
            ),
            array(
              '{(',
              ')}',
            ),
            $render);

          $render = html_entity_decode($render, ENT_QUOTES);

          $t = ($type == 'old') ? 'O' : 'N';

          $out[] = "{$t} {$num} {$htype} {$render}~";
          break;
        case 'no-context':
          $out[] = 'X <MISSING-CONTEXT>';
          break;
        default:
          $out[] = $type;
          break;
      }
    }

    if (!$any_old) {
      $out[] = 'O X <EMPTY>';
    }

    if (!$any_new) {
      $out[] = 'N X <EMPTY>';
    }

    $out = implode("\n", $out)."\n";
    return $out;
  }


  public function renderFileChange(
    $old_file = null,
    $new_file = null,
    $id = 0,
    $vs = 0) {

    throw new PhutilMethodNotImplementedException();
  }

}
