<?php

final class PhabricatorSourceCodeView extends AphrontView {

  private $lines;
  private $limit;
  private $uri;
  private $highlights = array();
  private $canClickHighlight = true;

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

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

  public function render() {
    require_celerity_resource('phabricator-source-code-view-css');
    require_celerity_resource('syntax-highlighting-css');

    Javelin::initBehavior('phabricator-oncopy', array());
    if ($this->canClickHighlight) {
      Javelin::initBehavior('phabricator-line-linker');
    }

    $line_number = 1;

    $rows = array();

    foreach ($this->lines as $line) {
      $hit_limit = $this->limit &&
                   ($line_number == $this->limit) &&
                   (count($this->lines) != $this->limit);

      if ($hit_limit) {
        $content_number = '';
        $content_line = phutil_tag(
          'span',
          array(
            'class' => 'c',
          ),
          pht('...'));
      } else {
        $content_number = $line_number;
        // NOTE: See phabricator-oncopy behavior.
        $content_line = hsprintf("\xE2\x80\x8B%s", $line);
      }

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

      if ($hit_limit) {
        break;
      }

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
