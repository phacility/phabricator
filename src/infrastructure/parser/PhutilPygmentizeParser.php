<?php

/**
 * Parser that converts `pygmetize` output or similar HTML blocks from "class"
 * attributes to "style" attributes.
 */
final class PhutilPygmentizeParser extends Phobject {

  private $map = array();

  public function setMap(array $map) {
    $this->map = $map;
    return $this;
  }

  public function getMap() {
    return $this->map;
  }

  public function parse($block) {
    $class_look = 'class="';
    $class_len = strlen($class_look);

    $class_start = null;

    $map = $this->map;

    $len = strlen($block);
    $out = '';
    $mode = 'text';
    for ($ii = 0; $ii < $len; $ii++) {
      $c = $block[$ii];
      switch ($mode) {
        case 'text':
          // We're in general text between tags, and just passing characers
          // through unmodified.
          if ($c == '<') {
            $mode = 'tag';
          }
          $out .= $c;
          break;
        case 'tag':
          // We're inside a tag, and looking for `class="` so we can rewrite
          // it.
          if ($c == '>') {
            $mode = 'text';
          }
          if ($c == 'c') {
            if (!substr_compare($block, $class_look, $ii, $class_len)) {
              $mode = 'class';
              $ii += $class_len;
              $class_start = $ii;
            }
          }

          if ($mode != 'class') {
            $out .= $c;
          }
          break;
        case 'class':
          // We're inside a `class="..."` tag, and looking for the ending quote
          // so we can replace it.
          if ($c == '"') {
            $class = substr($block, $class_start, $ii - $class_start);

            // If this class is present in the map, rewrite it into an inline
            // style attribute.
            if (isset($map[$class])) {
              $out .= 'style="'.phutil_escape_html($map[$class]).'"';
            } else {
              $out .= 'class="'.$class.'"';
            }

            $mode = 'tag';
          }
          break;
      }
    }

    return $out;
  }

}
