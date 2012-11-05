<?php

final class DivinerListController extends PhabricatorController {

  public function processRequest() {

    // TODO: Temporary implementation until Diviner is up and running inside
    // Phabricator.

    $links = array(
      'http://www.phabricator.com/docs/phabricator/' => array(
        'name'    => 'Phabricator Ducks',
        'flavor'  => 'Oops, that should say "Docs".',
      ),
      'http://www.phabricator.com/docs/arcanist/' => array(
        'name'    => 'Arcanist Docs',
        'flavor'  => 'Words have never been so finely crafted.',
      ),
      'http://www.phabricator.com/docs/libphutil/' => array(
        'name'    => 'libphutil Docs',
        'flavor'  => 'Soothing prose; seductive poetry.',
      ),
      'http://www.phabricator.com/docs/javelin/' => array(
        'name'    => 'Javelin Docs',
        'flavor'  => 'O, what noble scribe hath penned these words?',
      ),
    );

    require_celerity_resource('phabricator-directory-css');

    $out = array();
    foreach ($links as $href => $link) {
      $name = $link['name'];
      $flavor = $link['flavor'];

      $link = phutil_render_tag(
        'a',
        array(
          'href'    => $href,
          'target'  => '_blank',
        ),
        phutil_escape_html($name));

      $out[] =
        '<div class="aphront-directory-item">'.
          '<h1>'.$link.'</h1>'.
          '<p>'.phutil_escape_html($flavor).'</p>'.
        '</div>';
    }

    $out =
      '<div class="aphront-directory-list">'.
        implode("\n", $out).
      '</div>';

    return $this->buildApplicationPage(
      $out,
      array(
        'device' => true,
        'title' => 'Documentation',
      ));
  }
}
