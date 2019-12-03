<?php

final class PhutilRemarkupLinebreaksRule extends PhutilRemarkupRule {

  public function apply($text) {
    if ($this->getEngine()->isTextMode()) {
      return $text;
    }

    return phutil_escape_html_newlines($text);
  }

}
