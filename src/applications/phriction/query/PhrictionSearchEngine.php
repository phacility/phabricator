<?php

final class PhrictionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Wiki Documents');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhrictionApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('status', $request->getStr('status'));
    $saved->setParameter('order', $request->getStr('order'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhrictionDocumentQuery())
      ->needContent(true)
      ->withStatus(PhrictionDocumentQuery::STATUS_NONSTUB);

    $status = $saved->getParameter('status');
    $status = idx($this->getStatusValues(), $status);
    if ($status) {
      $query->withStatus($status);
    }

    $order = $saved->getParameter('order');
    $order = idx($this->getOrderValues(), $order);
    if ($order) {
      $query->setOrder($order);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setOptions($this->getStatusOptions())
          ->setValue($saved_query->getParameter('status')))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Order'))
          ->setName('order')
          ->setOptions($this->getOrderOptions())
          ->setValue($saved_query->getParameter('order')));
  }

  protected function getURI($path) {
    return '/phriction/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active'),
      'updated' => pht('Updated'),
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('status', 'active');
      case 'all':
        return $query;
      case 'updated':
        return $query->setParameter('order', 'updated');
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      'active' => pht('Show Active Documents'),
      'all' => pht('Show All Documents'),
    );
  }

  private function getStatusValues() {
    return array(
      'active' => PhrictionDocumentQuery::STATUS_OPEN,
      'all' => PhrictionDocumentQuery::STATUS_NONSTUB,
    );
  }

  private function getOrderOptions() {
    return array(
      'created' => pht('Date Created'),
      'updated' => pht('Date Updated'),
    );
  }

  private function getOrderValues() {
    return array(
      'created' => PhrictionDocumentQuery::ORDER_CREATED,
      'updated' => PhrictionDocumentQuery::ORDER_UPDATED,
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

    return $list;
  }

}
