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
        $content_line = phutil_tag(
          'span',
          array(
            'class' => 'c',
          ),
          pht('...'));
      } else {
        $content_number = $line_number;
        $content_line = hsprintf("\xE2\x80\x8B%s", $line);
      }

      // TODO: Provide nice links.

      $rows[] = hsprintf(
        '<tr>'.
          '<th class="phabricator-source-line">%s</th>'.
          '<td class="phabricator-source-code">%s</td>'.
        '</tr>',
        $content_number,
        $content_line);

      if ($hit_limit) {
        break;
      }

      $line_number++;
    }

    $classes = array();
    $classes[] = 'phabricator-source-code-view';
    $classes[] = 'remarkup-code';
    $classes[] = 'PhabricatorMonospaced';

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-source-code-container',
      ),
      phutil_tag(
        'table',
        array(
          'class' => implode(' ', $classes),
        ),
        phutil_implode_html('', $rows)));
  }

}
