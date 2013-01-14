<?php

abstract class DifferentialChangesetTestRenderer
  extends DifferentialChangesetRenderer {

  protected function renderChangeTypeHeader($force) {
    $changeset = $this->getChangeset();

    $old = nonempty($changeset->getOldFile(), '-');
    $away = nonempty(implode(', ', $changeset->getAwayPaths()), '-');
    $ctype = $changeset->getChangeType();
    $ftype = $changeset->getFileType();
    $force = ($force ? '(forced)' : '(unforced)');

    return "CTYPE {$ctype} {$ftype} {$force}\n".
           "{$old}\n".
           "{$away}\n";
  }

  public function renderShield($message, $force = 'default') {
    return "SHIELD ({$force}) {$message}\n";
  }

  protected function renderPropertyChangeHeader() {
    $changeset = $this->getChangeset();
    $old = $changeset->getOldProperties();
    $new = $changeset->getNewProperties();

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

    $primitives = $this->buildPrimitives($range_start, $range_len);
    foreach ($primitives as $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
          $num = nonempty($p['line'], '-');
          $render = $p['render'];
          $htype = nonempty($p['htype'], '.');

          // TODO: This should probably happen earlier, whenever we deal with
          // \r and \t normalization?
          $render = rtrim($render, "\r\n");
          $t = ($type == 'old') ? 'O' : 'N';

          $out[] = "{$t} {$num} {$htype} {$render}~";
          break;
        default:
          $out[] = $type;
          break;
      }
    }

    $out = implode("\n", $out)."\n";
    return $out;
  }


  public function renderFileChange($old_file = null,
                                   $new_file = null,
                                   $id = 0,
                                   $vs = 0) {
    throw new Exception("Not implemented!");
  }

}
