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

    // TODO: This query won't load ghosts, because they'll fail `needAtoms()`.
    // Instead, we might want to load ghosts and render a message like
    // "this thing existed in an older version, but no longer does", especially
    // if we add content like comments.

    $symbol = id(new DivinerAtomQuery())
      ->setViewer($viewer)
      ->withBookPHIDs(array($book->getPHID()))
      ->withTypes(array($this->atomType))
      ->withNames(array($this->atomName))
      ->withContexts(array($this->atomContext))
      ->withIndexes(array($this->atomIndex))
      ->needAtoms(true)
      ->executeOne();

    if (!$symbol) {
      return new Aphront404Response();
    }

    $atom = $symbol->getAtom();

    $crumbs = $this->buildApplicationCrumbs();

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($book->getShortTitle())
        ->setHref('/book/'.$book->getName().'/'));

    $atom_short_title = $atom->getDocblockMetaValue(
      'short',
      $symbol->getTitle());

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($atom_short_title));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($symbol->getTitle())
      ->addTag(
        id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_STATE)
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLUE)
          ->setName($this->renderAtomTypeName($atom->getType())));

    $properties = id(new PhabricatorPropertyListView());

    $group = $atom->getDocblockMetaValue('group');
    if ($group) {
      $group_name = $book->getGroupName($group);
    } else {
      $group_name = null;
    }

    $properties->addProperty(
      pht('Defined'),
      $atom->getFile().':'.$atom->getLine());

    $warnings = $atom->getWarnings();
    if ($warnings) {
      $warnings = id(new AphrontErrorView())
        ->setErrors($warnings)
        ->setTitle(pht('Documentation Warnings'))
        ->setSeverity(AphrontErrorView::SEVERITY_WARNING);
    }

    $field = 'default';
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->addObject($symbol, $field)
      ->process();

    $content = $engine->getOutput($symbol, $field);

    if (strlen(trim($symbol->getMarkupText($field)))) {
      $content = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-remarkup',
        ),
        array(
          $content,
        ));
    } else {
      $undoc = DivinerAtom::getThisAtomIsNotDocumentedString($atom->getType());
      $content = id(new AphrontErrorView())
        ->appendChild($undoc)
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA);
    }


    $toc = $engine->getEngineMetadata(
      $symbol,
      $field,
      PhutilRemarkupEngineRemarkupHeaderBlockRule::KEY_HEADER_TOC,
      array());

    $document = id(new PHUIDocumentView())
      ->setBook($book->getTitle(), $group_name)
      ->setHeader($header)
      ->appendChild($properties)
      ->appendChild($warnings)
      ->appendChild($content);

    $parameters = $atom->getProperty('parameters');
    if ($parameters !== null) {
      $document->appendChild(
        id(new PhabricatorHeaderView())
          ->setHeader(pht('Parameters')));

      $document->appendChild(
        id(new DivinerParameterTableView())
          ->setParameters($parameters));
    }

    $return = $atom->getProperty('return');
    if ($return !== null) {
      $document->appendChild(
        id(new PhabricatorHeaderView())
          ->setHeader(pht('Return')));
      $document->appendChild(
        id(new DivinerReturnTableView())
          ->setReturn($return));
    }

    if ($toc) {
      $side = new PHUIListView();
      $side->addMenuItem(
        id(new PHUIListItemView())
          ->setName(pht('Contents'))
          ->setType(PHUIListItemView::TYPE_LABEL));
      foreach ($toc as $key => $entry) {
        $side->addMenuItem(
          id(new PHUIListItemView())
            ->setName($entry[1])
            ->setHref('#'.$key));
      }

      $document->setSideNav($side, PHUIDocumentView::NAV_TOP);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => $symbol->getTitle(),
        'device' => true,
      ));
  }

  private function renderAtomTypeName($name) {
    return phutil_utf8_ucwords($name);
  }

}
