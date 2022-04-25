<?php

final class DivinerMainController extends DivinerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $books = id(new DivinerBookQuery())
      ->setViewer($viewer)
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(pht('Books'));

    $query_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($this->getApplicationURI('query/'))
      ->setText(pht('Advanced Search'))
      ->setIcon('fa-search');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Documentation Books'))
      ->addActionLink($query_button);

    $document = new PHUIDocumentView();
    $document->setHeader($header);
    $document->addClass('diviner-view');

    if ($books) {
      $books = msort($books, 'getTitle');
      $list = array();
      foreach ($books as $book) {
        $item = id(new DivinerBookItemView())
          ->setTitle($book->getTitle())
          ->setHref('/book/'.$book->getName().'/')
          ->setSubtitle($book->getPreface());
        $list[] = $item;
      }
      $list = id(new PHUIBoxView())
        ->addPadding(PHUI::PADDING_MEDIUM_TOP)
        ->appendChild($list);

      $document->appendChild($list);
    } else {
      $text = pht(
        "(NOTE) **Looking for documentation?** ".
        "If you're looking for help and information about %s, ".
        "you can [[https://secure.phabricator.com/diviner/ | ".
        "browse the public %s documentation]] on the live site.\n\n".
        "Diviner is the documentation generator used to build this ".
        "documentation.\n\n".
        "You haven't generated any Diviner documentation books yet, so ".
        "there's nothing to show here. If you'd like to generate your own ".
        "local copy of the documentation and have it appear ".
        "here, run this command:\n\n".
        "  %s\n\n",
        PlatformSymbols::getPlatformServerName(),
        PlatformSymbols::getPlatformServerName(),
        '$ ./bin/diviner generate');

      $text = new PHUIRemarkupView($viewer, $text);
      $document->appendChild($text);
    }

    return $this->newPage()
      ->setTitle(pht('Documentation Books'))
      ->setCrumbs($crumbs)
      ->appendChild(array(
        $document,
      ));
  }
}
