<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentListController extends LegalpadController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new LegalpadDocumentSearchEngine())
      ->setNavigation($this->buildSideNav());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $documents,
    PhabricatorSavedQuery $query) {
    assert_instances_of($documents, 'LegalpadDocument');

    $user = $this->getRequest()->getUser();

    $contributors = array_mergev(
      mpull($documents, 'getRecentContributorPHIDs'));
    $this->loadHandles($contributors);

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);
    foreach ($documents as $document) {
      $last_updated = phabricator_date($document->getDateModified(), $user);
      $updater = $this->getHandle(
        reset($document->getRecentContributorPHIDs()))->renderLink();

      $title = $document->getTitle();

      $item = id(new PhabricatorObjectItemView())
        ->setObjectName('L'.$document->getID())
        ->setHeader($title)
        ->setHref($this->getApplicationURI('view/'.$document->getID()))
        ->setObject($document)
        ->addIcon('none', pht('Last updated: %s', $last_updated))
        ->addByline(pht('Updated by: %s', $updater))
        ->addAttribute(pht('Versions: %d', $document->getVersions()));

      $list->addItem($item);
    }

    return $list;
  }

}
