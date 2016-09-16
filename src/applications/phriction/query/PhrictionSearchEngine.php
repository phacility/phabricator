<?php

final class PhrictionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Wiki Documents');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhrictionApplication';
  }

  public function newQuery() {
    return id(new PhrictionDocumentQuery())
      ->needContent(true)
      ->withStatus(PhrictionDocumentQuery::STATUS_NONSTUB);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['status']) {
      $query->withStatus($map['status']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchSelectField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setOptions($this->getStatusOptions()),
    );
  }

  protected function getURI($path) {
    return '/phriction/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active'),
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query->setParameter(
          'status', PhrictionDocumentQuery::STATUS_OPEN);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      PhrictionDocumentQuery::STATUS_OPEN => pht('Show Active Documents'),
      PhrictionDocumentQuery::STATUS_NONSTUB => pht('Show All Documents'),
    );
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $documents,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($documents as $document) {
      $content = $document->getContent();
      $phids[] = $content->getAuthorPHID();
    }

    return $phids;
  }


  protected function renderResultList(
    array $documents,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($documents, 'PhrictionDocument');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($documents as $document) {
      $content = $document->getContent();
      $slug = $document->getSlug();
      $author_phid = $content->getAuthorPHID();
      $slug_uri = PhrictionDocument::getSlugURI($slug);

      $byline = pht(
        'Edited by %s',
        $handles[$author_phid]->renderLink());

      $updated = phabricator_datetime(
        $content->getDateCreated(),
        $viewer);

      $item = id(new PHUIObjectItemView())
        ->setHeader($content->getTitle())
        ->setHref($slug_uri)
        ->addByline($byline)
        ->addIcon('none', $updated);

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

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No documents found.'));

    return $result;
  }

}
