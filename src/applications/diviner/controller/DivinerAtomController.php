<?php

final class DivinerAtomController extends DivinerController {

  private $bookName;
  private $atomType;
  private $atomName;
  private $atomContext;
  private $atomIndex;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->bookName = $data['book'];
    $this->atomType = $data['type'];
    $this->atomName = $data['name'];
    $this->atomContext = nonempty(idx($data, 'context'), null);
    $this->atomIndex = nonempty(idx($data, 'index'), null);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $book = id(new DivinerBookQuery())
      ->setViewer($viewer)
      ->withNames(array($this->bookName))
      ->executeOne();

    if (!$book) {
      return new Aphront404Response();
    }

    $atom = id(new DivinerAtomQuery())
      ->setViewer($viewer)
      ->withBookPHIDs(array($book->getPHID()))
      ->withTypes(array($this->atomType))
      ->withNames(array($this->atomName))
      ->withContexts(array($this->atomContext))
      ->withIndexes(array($this->atomIndex))
      ->needAtoms(true)
      ->executeOne();

    if (!$atom) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($book->getName())
        ->setHref('/book/'.$book->getName().'/'));

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($atom->getName()));

    $header = id(new PhabricatorHeaderView())->setHeader($atom->getName());

    $document = id(new PHUIDocumentView())
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          phutil_safe_html($atom->getContent())));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => $atom->getName(),
        'dust' => true,
        'device' => true,
      ));
  }

}
