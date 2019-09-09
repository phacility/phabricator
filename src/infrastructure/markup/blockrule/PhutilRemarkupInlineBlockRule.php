<?php

final class PhutilRemarkupInlineBlockRule extends PhutilRemarkupBlockRule {

  public function getMatchingLineCount(array $lines, $cursor) {
    return 1;
  }

  public function markupText($text, $children) {
    return $this->applyRules($text);
  }

}
