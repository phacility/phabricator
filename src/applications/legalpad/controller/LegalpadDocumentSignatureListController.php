<?php

final class LegalpadDocumentSignatureListController extends LegalpadController {

  private $documentID;
  private $queryKey;
  private $document;

  public function willProcessRequest(array $data) {
    $this->documentID = idx($data, 'id');
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->documentID) {
      $document = id(new LegalpadDocumentQuery())
        ->setViewer($user)
        ->withIDs(array($this->documentID))
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
      ->setQueryKey($this->queryKey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNav());

    return $this->delegateToController($controller);
  }

  public function buildSideNav($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new LegalpadDocumentSignatureSearchEngine())
      ->setViewer($user);

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
