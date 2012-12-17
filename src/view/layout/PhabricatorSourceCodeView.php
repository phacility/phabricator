<?php

final class PhabricatorSourceCodeView extends AphrontView {

  private $lines;
  private $limit;

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setLines(array $lines) {
    $this->lines = $lines;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-source-code-view-css');
    require_celerity_resource('syntax-highlighting-css');

    Javelin::initBehavior('phabricator-oncopy', array());

    $line_number = 1;

    $rows = array();
    foreach ($this->lines as $line) {
      $hit_limit = $this->limit &&
                   ($line_number == $this->limit) &&
                   (count($this->lines) != $this->limit);

      if ($hit_limit) {
        $content_number = '';
        $content_line = phutil_render_tag(
          'span',
          array(
            'class' => 'c',
          ),
          pht('...'));
      } else {
        $content_number = phutil_escape_html($line_number);
        $content_line = "\xE2\x80\x8B".$line;
      }

      // TODO: Provide nice links.

      $rows[] =
        '<tr>'.
          '<th class="phabricator-source-line">'.
            $content_number.
          '</th>'.
          '<td class="phabricator-source-code">'.
            $content_line.
          '</td>'.
        '</tr>';

      if ($hit_limit) {
        break;
      }

      $line_number++;
    }

    $classes = array();
    $classes[] = 'phabricator-source-code-view';
    $classes[] = 'remarkup-code';
    $classes[] = 'PhabricatorMonospaced';

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-source-code-container',
      ),
      phutil_render_tag(
        'table',
        array(
          'class' => implode(' ', $classes),
        ),
        implode('', $rows)));
  }

}
