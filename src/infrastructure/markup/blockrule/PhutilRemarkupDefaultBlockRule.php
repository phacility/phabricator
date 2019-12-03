<?php

final class PhutilRemarkupDefaultBlockRule extends PhutilRemarkupBlockRule {

  public function getPriority() {
    return 750;
  }

  public function getMatchingLineCount(array $lines, $cursor) {
    return 1;
  }

  public function markupText($text, $children) {
    $engine = $this->getEngine();

    $text = trim($text);
    $text = $this->applyRules($text);

    if ($engine->isTextMode()) {
      if (!$this->getEngine()->getConfig('preserve-linebreaks')) {
        $text = preg_replace('/ *\n */', ' ', $text);
      }
      return $text;
    }

    if ($engine->getConfig('preserve-linebreaks')) {
      $text = phutil_escape_html_newlines($text);
    }

    if (!strlen($text)) {
      return null;
    }

    $default_attributes = $engine->getConfig('default.p.attributes');
    if ($default_attributes) {
      $attributes = $default_attributes;
    } else {
      $attributes = array();
    }

    return phutil_tag('p', $attributes, $text);
  }

}
