<?php

final class PhrictionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('status', $request->getArr('status'));
    $saved->setParameter('order', $request->getArr('order'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhrictionDocumentQuery())
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

  public function getBuiltinQueryNames() {
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

}
