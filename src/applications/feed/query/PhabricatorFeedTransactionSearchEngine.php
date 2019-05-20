<?php

final class PhabricatorFeedTransactionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Transactions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFeedApplication';
  }

  public function newQuery() {
    return new PhabricatorFeedTransactionQuery();
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function getURI($path) {
    return '/feed/transactions/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Transactions'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery()
      ->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $objects,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($objects, 'PhabricatorApplicationTransaction');

    $viewer = $this->requireViewer();

    $handle_phids = array();
    foreach ($objects as $object) {
      $author_phid = $object->getAuthorPHID();
      if ($author_phid !== null) {
        $handle_phids[] = $author_phid;
      }
      $object_phid = $object->getObjectPHID();
      if ($object_phid !== null) {
        $handle_phids[] = $object_phid;
      }
    }

    $handles = $viewer->loadHandles($handle_phids);

    $rows = array();
    foreach ($objects as $object) {
      $author_phid = $object->getAuthorPHID();
      $object_phid = $object->getObjectPHID();

      try {
        $title = $object->getTitle();
      } catch (Exception $ex) {
        $title = null;
      }

      $rows[] = array(
        $handles[$author_phid]->renderLink(),
        $handles[$object_phid]->renderLink(),
        AphrontTableView::renderSingleDisplayLine($title),
        phabricator_datetime($object->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Actor'),
          pht('Object'),
          pht('Transaction'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide',
          'right',
        ));

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

}
