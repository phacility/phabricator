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

    $list = new PHUIObjectItemListView();
    $list->setUser($user);
    foreach ($documents as $document) {
      $last_updated = phabricator_date($document->getDateModified(), $user);
      $recent_contributors = $document->getRecentContributorPHIDs();
      $updater = $this->getHandle(reset($recent_contributors))->renderLink();

      $title = $document->getTitle();

      $item = id(new PHUIObjectItemView())
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
