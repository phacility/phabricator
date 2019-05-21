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
    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Object Types'))
        ->setKey('objectTypes')
        ->setAliases(array('objectType'))
        ->setDatasource(new PhabricatorTransactionsObjectTypeDatasource()),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['objectTypes']) {
      $query->withObjectTypes($map['objectTypes']);
    }

    $created_min = $map['createdStart'];
    $created_max = $map['createdEnd'];

    if ($created_min && $created_max) {
      if ($created_min > $created_max) {
        throw new PhabricatorSearchConstraintException(
          pht(
            'The specified "Created Before" date is earlier in time than the '.
            'specified "Created After" date, so this query can never match '.
            'any results.'));
      }
    }

    if ($created_min || $created_max) {
      $query->withDateCreatedBetween($created_min, $created_max);
    }

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
          pht('Author'),
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
