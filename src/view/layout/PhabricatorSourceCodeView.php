<?php

final class PhabricatorSourceCodeView extends AphrontView {

  private $lines;
  private $uri;
  private $highlights = array();
  private $canClickHighlight = true;
  private $truncatedFirstBytes = false;
  private $truncatedFirstLines = false;

  public function setLines(array $lines) {
    $this->lines = $lines;
    return $this;
  }

  public function setURI(PhutilURI $uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setHighlights(array $array) {
    $this->highlights = array_fuse($array);
    return $this;
  }

  public function disableHighlightOnClick() {
    $this->canClickHighlight = false;
    return $this;
  }

  public function setTruncatedFirstBytes($truncated_first_bytes) {
    $this->truncatedFirstBytes = $truncated_first_bytes;
    return $this;
  }

  public function setTruncatedFirstLines($truncated_first_lines) {
    $this->truncatedFirstLines = $truncated_first_lines;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-source-code-view-css');
    require_celerity_resource('syntax-highlighting-css');

    Javelin::initBehavior('phabricator-oncopy', array());
    if ($this->canClickHighlight) {
      Javelin::initBehavior('phabricator-line-linker');
    }

    $line_number = 1;

    $rows = array();

    $lines = $this->lines;
    if ($this->truncatedFirstLines) {
      $lines[] = phutil_tag(
          'span',
          array(
            'class' => 'c',
          ),
          pht('...'));
    } else if ($this->truncatedFirstBytes) {
      $last_key = last_key($lines);
      $lines[$last_key] = hsprintf(
        '%s%s',
        $lines[$last_key],
        phutil_tag(
          'span',
          array(
            'class' => 'c',
          ),
          pht('...')));
    }

    foreach ($lines as $line) {

      // NOTE: See phabricator-oncopy behavior.
      $content_line = hsprintf("\xE2\x80\x8B%s", $line);

      $row_attributes = array();
      if (isset($this->highlights[$line_number])) {
        $row_attributes['class'] = 'phabricator-source-highlight';
      }

      if ($this->canClickHighlight) {
        $line_uri = $this->uri.'$'.$line_number;
        $line_href = (string)new PhutilURI($line_uri);

        $tag_number = javelin_tag(
          'a',
          array(
            'href' => $line_href,
          ),
          $line_number);
      } else {
        $tag_number = javelin_tag(
          'span',
          array(),
          $line_number);
      }

      $rows[] = phutil_tag(
        'tr',
        $row_attributes,
        array(
          javelin_tag(
            'th',
            array(
              'class' => 'phabricator-source-line',
              'sigil' => 'phabricator-source-line',
            ),
            $tag_number),
          phutil_tag(
            'td',
            array(
              'class' => 'phabricator-source-code',
            ),
            $content_line),
          ));

      $line_number++;
    }

    $classes = array();
    $classes[] = 'phabricator-source-code-view';
    $classes[] = 'remarkup-code';
    $classes[] = 'PhabricatorMonospaced';

    return phutil_tag_div(
      'phabricator-source-code-container',
      javelin_tag(
        'table',
        array(
          'class' => implode(' ', $classes),
          'sigil' => 'phabricator-source',
        ),
        phutil_implode_html('', $rows)));
  }

}
