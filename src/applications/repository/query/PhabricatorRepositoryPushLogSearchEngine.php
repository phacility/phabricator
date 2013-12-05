<?php

final class PhabricatorRepositoryPushLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'repositoryPHIDs',
      $this->readPHIDsFromRequest(
        $request,
        'repositories',
        array(
          PhabricatorRepositoryPHIDTypeRepository::TYPECONST,
        )));

    $saved->setParameter(
      'pusherPHIDs',
      $this->readUsersFromRequest(
        $request,
        'pushers'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorRepositoryPushLogQuery());

    $repository_phids = $saved->getParameter('repositoryPHIDs');
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    $pusher_phids = $saved->getParameter('pusherPHIDs');
    if ($pusher_phids) {
      $query->withPusherPHIDs($pusher_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $repository_phids = $saved_query->getParameter('repositoryPHIDs', array());
    $pusher_phids = $saved_query->getParameter('pusherPHIDs', array());

    $all_phids = array_merge(
      $repository_phids,
      $pusher_phids);

    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $repository_handles = array_select_keys($handles, $repository_phids);
    $pusher_handles = array_select_keys($handles, $pusher_phids);

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/repositories/')
          ->setName('repositories')
          ->setLabel(pht('Repositories'))
          ->setValue($repository_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('pushers')
          ->setLabel(pht('Pushers'))
          ->setValue($pusher_handles));
  }

  protected function getURI($path) {
    return '/diffusion/pushlog/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Push Logs'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
