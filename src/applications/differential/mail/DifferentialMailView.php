<?php

abstract class DifferentialMailView
  extends Phobject {

  protected function renderCodeBlock($block) {
    $style = array(
      'font: 11px/15px "Menlo", "Consolas", "Monaco", monospace;',
      'white-space: pre-wrap;',
      'clear: both;',
      'padding: 4px 0;',
      'margin: 0;',
    );

    return phutil_tag(
      'div',
      array(
        'style' => implode(' ', $style),
      ),
      $block);
  }

  protected function renderHeaderBlock($block) {
    $style = array(
      'color: #74777d;',
      'background: #eff2f4;',
      'padding: 6px 8px;',
      'overflow: hidden;',
    );

    return phutil_tag(
      'div',
      array(
        'style' => implode(' ', $style),
      ),
      $block);
  }

  protected function renderHeaderBold($content) {
    return phutil_tag(
      'span',
      array(
        'style' => 'color: #4b4d51; font-weight: bold;',
      ),
      $content);
  }

  protected function renderContentBox($content) {
    $style = array(
      'border: 1px solid #C7CCD9;',
      'border-radius: 3px;',
    );

    return phutil_tag(
      'div',
      array(
        'style' => implode(' ', $style),
      ),
      $content);
  }

}
