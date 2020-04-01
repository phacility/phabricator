<?php

/**
 * Highlights source code with a rainbow of colors, regardless of the language.
 * This highlighter is useless, absurd, and extremely slow.
 */
final class PhutilRainbowSyntaxHighlighter extends Phobject {

  private $config = array();

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getHighlightFuture($source) {

    $color = 0;
    $colors = array(
      'rbw_r',
      'rbw_o',
      'rbw_y',
      'rbw_g',
      'rbw_b',
      'rbw_i',
      'rbw_v',
    );

    $result = array();
    foreach (phutil_utf8v($source) as $character) {
      if ($character == ' ' || $character == "\n") {
        $result[] = $character;
        continue;
      }
      $result[] = phutil_tag(
        'span',
        array('class' => $colors[$color]),
        $character);
      $color = ($color + 1) % count($colors);
    }

    $result = phutil_implode_html('', $result);
    return new ImmediateFuture($result);
  }

}
