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

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {

    $primitives = $this->buildPrimitives($range_start, $range_len);
    return $this->renderPrimitives($primitives, $rows);
  }

  protected function renderPrimitives(array $primitives, $rows) {
    $out = array();

    $viewer = $this->getUser();
    $old_bright = $viewer->getCSSValue('old-bright');
    $new_bright = $viewer->getCSSValue('new-bright');

    $context_style = array(
      'background: #F7F7F7;',
      'color: #74777D;',
      'border-style: dashed;',
      'border-color: #C7CCD9;',
      'border-width: 1px 0;',
    );

    $context_style = implode(' ', $context_style);

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
              $style = "background: {$old_bright};";
            } else {
              $style = null;
            }
          } else {
            if ($p['htype']) {
              $style = "background: {$new_bright};";
            } else {
              $style =  null;
            }
          }

          $out[] = array(
            'style' => $style,
            'render' => $p['render'],
            'text' => (string)$p['render'],
          );
          break;
        case 'context':
          // NOTE: These are being included with no text so they get stripped
          // in the header and footer.
          $out[] = array(
            'style' => $context_style,
            'render' => '...',
            'text' => '',
          );
          break;
        default:
          break;
      }
    }

    // Remove all leading and trailing empty lines, since these just look kind
    // of weird in mail.
    foreach ($out as $key => $line) {
      if (!strlen(trim($line['text']))) {
        unset($out[$key]);
      } else {
        break;
      }
    }

    $keys = array_reverse(array_keys($out));
    foreach ($keys as $key) {
      $line = $out[$key];
      if (!strlen(trim($line['text']))) {
        unset($out[$key]);
      } else {
        break;
      }
    }

    // If the user has commented on an empty line in the middle of a bunch of
    // other empty lines, emit an explicit marker instead of just rendering
    // nothing.
    if (!$out) {
      $out[] = array(
        'style' => 'color: #888888;',
        'render' => pht('(Empty.)'),
      );
    }

    $render = array();
    foreach ($out as $line) {
      $style = $line['style'];
      $style = "padding: 0 8px; margin: 0 4px; {$style}";

      $render[] = phutil_tag(
        'div',
        array(
          'style' => $style,
        ),
        $line['render']);
    }

    $style_map = id(new PhabricatorDefaultSyntaxStyle())
      ->getRemarkupStyleMap();

    $styled_body = id(new PhutilPygmentizeParser())
      ->setMap($style_map)
      ->parse((string)hsprintf('%s', $render));

    return phutil_safe_html($styled_body);
  }

}
