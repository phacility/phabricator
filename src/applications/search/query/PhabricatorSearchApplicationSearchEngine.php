<?php

final class PhabricatorSearchApplicationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Fulltext Results');
  }

  public function getApplicationClassName() {
    return 'PhabricatorSearchApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('query', $request->getStr('query'));
    $saved->setParameter(
      'statuses',
      $this->readListFromRequest($request, 'statuses'));
    $saved->setParameter(
      'types',
      $this->readListFromRequest($request, 'types'));

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authorPHIDs'));

    $saved->setParameter(
      'ownerPHIDs',
      $this->readUsersFromRequest($request, 'ownerPHIDs'));

    $saved->setParameter(
      'withUnowned',
      $this->readBoolFromRequest($request, 'withUnowned'));

    $saved->setParameter(
      'subscriberPHIDs',
      $this->readPHIDsFromRequest($request, 'subscriberPHIDs'));

    $saved->setParameter(
      'projectPHIDs',
      $this->readPHIDsFromRequest($request, 'projectPHIDs'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorSearchDocumentQuery())
      ->withSavedQuery($saved);
    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $options = array();
    $author_value = null;
    $owner_value = null;
    $subscribers_value = null;
    $project_value = null;

    $author_phids = $saved->getParameter('authorPHIDs', array());
    $owner_phids = $saved->getParameter('ownerPHIDs', array());
    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());
    $project_phids = $saved->getParameter('projectPHIDs', array());

    $all_phids = array_merge(
      $author_phids,
      $owner_phids,
      $subscriber_phids,
      $project_phids);

    $all_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($all_phids)
      ->execute();

    $author_handles = array_select_keys($all_handles, $author_phids);
    $owner_handles = array_select_keys($all_handles, $owner_phids);
    $subscriber_handles = array_select_keys($all_handles, $subscriber_phids);
    $project_handles = array_select_keys($all_handles, $project_phids);

    $with_unowned = $saved->getParameter('withUnowned', array());

    $status_values = $saved->getParameter('statuses', array());
    $status_values = array_fuse($status_values);

    $statuses = array(
      PhabricatorSearchRelationship::RELATIONSHIP_OPEN => pht('Open'),
      PhabricatorSearchRelationship::RELATIONSHIP_CLOSED => pht('Closed'),
    );
    $status_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Document Status'));
    foreach ($statuses as $status => $name) {
      $status_control->addCheckbox(
        'statuses[]',
        $status,
        $name,
        isset($status_values[$status]));
    }

    $type_values = $saved->getParameter('types', array());
    $type_values = array_fuse($type_values);

    $types = self::getIndexableDocumentTypes($this->requireViewer());

    $types_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Document Types'));
    foreach ($types as $type => $name) {
      $types_control->addCheckbox(
        'types[]',
        $type,
        $name,
        isset($type_values[$type]));
    }

    $form
      ->appendChild(
        phutil_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'jump',
            'value' => 'no',
          )))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Query')
          ->setName('query')
          ->setValue($saved->getParameter('query')))
      ->appendChild($status_control)
      ->appendChild($types_control)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('authorPHIDs')
          ->setLabel('Authors')
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setValue($author_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('ownerPHIDs')
          ->setLabel('Owners')
          ->setDatasource(new PhabricatorTypeaheadOwnerDatasource())
          ->setValue($owner_handles))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'withUnowned',
            1,
            pht('Show only unowned documents.'),
            $with_unowned))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('subscriberPHIDs')
          ->setLabel('Subscribers')
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setValue($subscriber_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('projectPHIDs')
          ->setLabel('In Any Project')
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setValue($project_handles));
  }

  protected function getURI($path) {
    return '/search/'.$path;
  }

  public function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Documents'),
      'open' => pht('Open Documents'),
      'open-tasks' => pht('Open Tasks'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'open':
        return $query->setParameter('statuses', array('open'));
      case 'open-tasks':
        return $query
          ->setParameter('statuses', array('open'))
          ->setParameter('types', array(ManiphestTaskPHIDType::TYPECONST));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  public static function getIndexableDocumentTypes(
    PhabricatorUser $viewer = null) {

    // TODO: This is inelegant and not very efficient, but gets us reasonable
    // results. It would be nice to do this more elegantly.

    $indexers = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorSearchDocumentIndexer')
      ->loadObjects();

    if ($viewer) {
      $types = PhabricatorPHIDType::getAllInstalledTypes($viewer);
    } else {
      $types = PhabricatorPHIDType::getAllTypes();
    }

    $results = array();
    foreach ($types as $type) {
      $typeconst = $type->getTypeConstant();
      foreach ($indexers as $indexer) {
        $fake_phid = 'PHID-'.$typeconst.'-fake';
        if ($indexer->shouldIndexDocumentByPHID($fake_phid)) {
          $results[$typeconst] = $type->getTypeName();
        }
      }
    }

    asort($results);

    // Put tasks first, see T4606.
    $results = array_select_keys(
      $results,
      array(
        ManiphestTaskPHIDType::TYPECONST,
      )) + $results;

    return $results;
  }

  public function shouldUseOffsetPaging() {
    return true;
  }

  protected function renderResultList(
    array $results,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();

    if ($results) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($results, 'getPHID'))
        ->execute();

      $output = array();
      foreach ($results as $phid => $handle) {
        $view = id(new PhabricatorSearchResultView())
          ->setHandle($handle)
          ->setQuery($query)
          ->setObject(idx($objects, $phid));
        $output[] = $view->render();
      }

      $results = phutil_tag_div(
        'phabricator-search-result-list',
        $output);
    } else {
      $results = phutil_tag_div(
        'phabricator-search-result-list',
        phutil_tag(
          'p',
          array('class' => 'phabricator-search-no-results'),
          pht('No search results.')));
    }

    return id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->addPadding(PHUI::PADDING_LARGE)
      ->setBorder(true)
      ->appendChild($results)
      ->addClass('phabricator-search-result-box');
  }

}
