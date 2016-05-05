<?php

final class DifferentialChangesetOneUpMailRenderer
  extends DifferentialChangesetRenderer {

  public function isOneUpRenderer() {
    return true;
  }

  protected function getRendererTableClass() {
    return 'diff-1up-mail';
  }

  public function getRendererKey() {
    return '1up-mail';
  }

  protected function renderChangeTypeHeader($force) {
    return null;
  }

  protected function renderUndershieldHeader() {
    return null;
  }

  public function renderShield($message, $force = 'default') {
    return null;
  }

  protected function renderPropertyChangeHeader() {
    return null;
  }

  public function renderFileChange(
    $old_file = null,
    $new_file = null,
    $id = 0,
    $vs = 0) {
    return null;
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $primitives = $this->buildPrimitives($range_start, $range_len);
    return $this->renderPrimitives($primitives, $rows);
  }

  protected function renderPrimitives(array $primitives, $rows) {
    $out = array();
    foreach ($primitives as $k => $p) {
      $type = $p['type'];
      switch ($type) {
        case 'old':
        case 'new':
        case 'old-file':
        case 'new-file':
          $is_old = ($type == 'old' || $type == 'old-file');

          if ($is_old) {
            if ($p['htype']) {
              $style = 'background: #ffd0d0;';
            } else {
              $style = null;
            }
          } else {
            if ($p['htype']) {
              $style = 'background: #d0ffd0;';
            } else {
              $style =  null;
            }
          }

          $out[] = phutil_tag(
            'div',
            array(
              'style' => $style,
            ),
            $p['render']);
          break;
        default:
          break;
      }
    }

    $style_map = id(new PhabricatorDefaultSyntaxStyle())
      ->getRemarkupStyleMap();

    $styled_body = id(new PhutilPygmentizeParser())
      ->setMap($style_map)
      ->parse((string)hsprintf('%s', $out));

    return phutil_safe_html($styled_body);
  }

}
