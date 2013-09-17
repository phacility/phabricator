<?php

final class DivinerLegacyController extends DivinerController {

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

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setPlain(true);

    foreach ($links as $href => $link) {
      $item = id(new PHUIObjectItemView())
        ->setHref($href)
        ->setHeader($link['name'])
        ->addAttribute($link['flavor']);

      $list->addItem($item);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Documentation')));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Documentation'));

    $document = new PHUIDocumentView();
    $document->setHeader($header);
    $document->appendChild($list);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => pht('Documentation'),
        'device' => true,
      ));
  }
}
