<?php

final class LegalpadDocumentSignatureListController extends LegalpadController {

  private $document;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $querykey = $request->getURIData('queryKey');

    if ($id) {
      $document = id(new LegalpadDocumentQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$document) {
        return new Aphront404Response();
      }

      $this->document = $document;
    }

    $engine = id(new LegalpadDocumentSignatureSearchEngine());

    if ($this->document) {
      $engine->setDocument($this->document);
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNav());

    return $this->delegateToController($controller);
  }

  public function buildSideNav($for_app = false) {
    $viewer = $this->getViewer();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new LegalpadDocumentSignatureSearchEngine())
      ->setViewer($viewer);

    if ($this->document) {
      $engine->setDocument($this->document);
    }

    $engine->addNavigationItems($nav->getMenu());

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->document) {
      $crumbs->addTextCrumb(
        $this->document->getMonogram(),
        '/'.$this->document->getMonogram());
      $crumbs->addTextCrumb(
        pht('Manage'),
        $this->getApplicationURI('view/'.$this->document->getID().'/'));
    } else {
      $crumbs->addTextCrumb(
        pht('Signatures'),
        '/legalpad/signatures/');
    }

    return $crumbs;
  }

}
