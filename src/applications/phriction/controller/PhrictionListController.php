<?php

final class PhrictionListController
  extends PhrictionController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhrictionSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $documents,
    PhabricatorSavedQuery $query) {
    assert_instances_of($documents, 'PhrictionDocument');

    $viewer = $this->getRequest()->getUser();

    $phids = array();
    foreach ($documents as $document) {
      $content = $document->getContent();
      if ($document->hasProject()) {
        $phids[] = $document->getProject()->getPHID();
      }
      $phids[] = $content->getAuthorPHID();
    }

    $this->loadHandles($phids);

    $list = new PhabricatorObjectItemListView();
    $list->setUser($viewer);
    foreach ($documents as $document) {
      $content = $document->getContent();
      $slug = $document->getSlug();
      $author_phid = $content->getAuthorPHID();
      $slug_uri = PhrictionDocument::getSlugURI($slug);

      $byline = pht(
        'Edited by %s',
        $this->getHandle($author_phid)->renderLink());

      $updated = phabricator_datetime(
        $content->getDateCreated(),
        $viewer);

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($content->getTitle())
        ->setHref($slug_uri)
        ->addByline($byline)
        ->addIcon('none', $updated);

      if ($document->hasProject()) {
        $item->addAttribute(
          $this->getHandle($document->getProject()->getPHID())->renderLink());
      }

      $item->addAttribute($slug_uri);

      switch ($document->getStatus()) {
        case PhrictionDocumentStatus::STATUS_DELETED:
          $item->setDisabled(true);
          $item->addIcon('delete', pht('Deleted'));
          break;
        case PhrictionDocumentStatus::STATUS_MOVED:
          $item->setDisabled(true);
          $item->addIcon('arrow-right', pht('Moved Away'));
          break;
      }

      $list->addItem($item);
    }

    return $list;
  }

}
