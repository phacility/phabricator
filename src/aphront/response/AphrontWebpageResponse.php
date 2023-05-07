<?php

final class AphrontWebpageResponse extends AphrontHTMLResponse {

  private $content;
  private $unexpectedOutput;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setUnexpectedOutput($unexpected_output) {
    $this->unexpectedOutput = $unexpected_output;
    return $this;
  }

  public function getUnexpectedOutput() {
    return $this->unexpectedOutput;
  }

  public function buildResponseString() {
    $unexpected_output = $this->getUnexpectedOutput();
    if ($unexpected_output !== null && strlen($unexpected_output)) {
      $style = array(
        'background: linear-gradient(180deg, #eeddff, #ddbbff);',
        'white-space: pre-wrap;',
        'z-index: 200000;',
        'position: relative;',
        'padding: 16px;',
        'font-family: monospace;',
        'text-shadow: 1px 1px 1px white;',
      );

      $unexpected_header = phutil_tag(
        'div',
        array(
          'style' => implode(' ', $style),
        ),
        $unexpected_output);
    } else {
      $unexpected_header = '';
    }

    return hsprintf('%s%s', $unexpected_header, $this->content);
  }

}
