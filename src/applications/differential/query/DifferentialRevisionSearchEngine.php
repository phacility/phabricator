<?php

final class DifferentialRevisionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Differential Revisions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getPageSize(PhabricatorSavedQuery $saved) {
    if ($saved->getQueryKey() == 'active') {
      return 0xFFFF;
    }
    return parent::getPageSize($saved);
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'responsiblePHIDs',
      $this->readUsersFromRequest($request, 'responsibles'));

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter(
      'reviewerPHIDs',
      $this->readUsersFromRequest(
        $request,
        'reviewers',
        array(
          PhabricatorProjectProjectPHIDType::TYPECONST,
        )));

    $saved->setParameter(
      'subscriberPHIDs',
      $this->readSubscribersFromRequest($request, 'subscribers'));

    $saved->setParameter(
      'repositoryPHIDs',
      $request->getArr('repositories'));

    $saved->setParameter(
      'projects',
      $this->readProjectsFromRequest($request, 'projects'));

    $saved->setParameter(
      'draft',
      $request->getBool('draft'));

    $saved->setParameter(
      'order',
      $request->getStr('order'));

    $saved->setParameter(
      'status',
      $request->getStr('status'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new DifferentialRevisionQuery())
      ->needFlags(true)
      ->needDrafts(true)
      ->needRelationships(true);

    $user_datasource = id(new PhabricatorPeopleUserFunctionDatasource())
      ->setViewer($this->requireViewer());

    $responsible_phids = $saved->getParameter('responsiblePHIDs', array());
    $responsible_phids = $user_datasource->evaluateTokens($responsible_phids);
    if ($responsible_phids) {
      $query->withResponsibleUsers($responsible_phids);
    }

    $this->setQueryProjects($query, $saved);

    $author_phids = $saved->getParameter('authorPHIDs', array());
    $author_phids = $user_datasource->evaluateTokens($author_phids);
    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $reviewer_phids = $saved->getParameter('reviewerPHIDs', array());
    if ($reviewer_phids) {
      $query->withReviewers($reviewer_phids);
    }

    $sub_datasource = id(new PhabricatorMetaMTAMailableFunctionDatasource())
      ->setViewer($this->requireViewer());
    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());
    $subscriber_phids = $sub_datasource->evaluateTokens($subscriber_phids);
    if ($subscriber_phids) {
      $query->withCCs($subscriber_phids);
    }

    $repository_phids = $saved->getParameter('repositoryPHIDs', array());
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    $draft = $saved->getParameter('draft', false);
    if ($draft && $this->requireViewer()->isLoggedIn()) {
      $query->withDraftRepliesByAuthors(
        array($this->requireViewer()->getPHID()));
    }

    $status = $saved->getParameter('status');
    if (idx($this->getStatusOptions(), $status)) {
      $query->withStatus($status);
    }

    $order = $saved->getParameter('order');
    if (idx($this->getOrderOptions(), $order)) {
      $query->setOrder($order);
    } else {
      $query->setOrder(DifferentialRevisionQuery::ORDER_CREATED);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $responsible_phids = $saved->getParameter('responsiblePHIDs', array());
    $author_phids = $saved->getParameter('authorPHIDs', array());
    $reviewer_phids = $saved->getParameter('reviewerPHIDs', array());
    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());
    $repository_phids = $saved->getParameter('repositoryPHIDs', array());
    $only_draft = $saved->getParameter('draft', false);
    $projects = $saved->getParameter('projects', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Responsible Users'))
          ->setName('responsibles')
          ->setDatasource(new PhabricatorPeopleUserFunctionDatasource())
          ->setValue($responsible_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Authors'))
          ->setName('authors')
          ->setDatasource(new PhabricatorPeopleUserFunctionDatasource())
          ->setValue($author_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Reviewers'))
          ->setName('reviewers')
          ->setDatasource(new PhabricatorProjectOrUserDatasource())
          ->setValue($reviewer_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Subscribers'))
          ->setName('subscribers')
          ->setDatasource(new PhabricatorMetaMTAMailableFunctionDatasource())
          ->setValue($subscriber_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Repositories'))
          ->setName('repositories')
          ->setDatasource(new DiffusionRepositoryDatasource())
          ->setValue($repository_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setDatasource(new PhabricatorProjectLogicalDatasource())
          ->setValue($projects))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setOptions($this->getStatusOptions())
          ->setValue($saved->getParameter('status')));

    if ($this->requireViewer()->isLoggedIn()) {
      $form
        ->appendChild(
          id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              'draft',
              1,
              pht('Show only revisions with a draft comment.'),
              $only_draft));
    }

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Order'))
          ->setName('order')
          ->setOptions($this->getOrderOptions())
          ->setValue($saved->getParameter('order')));
  }

  protected function getURI($path) {
    return '/differential/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['active'] = pht('Active Revisions');
      $names['authored'] = pht('Authored');
    }

    $names['all'] = pht('All Revisions');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'active':
        return $query
          ->setParameter('responsiblePHIDs', array($viewer->getPHID()))
          ->setParameter('status', DifferentialRevisionQuery::STATUS_OPEN);
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer->getPHID()));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      DifferentialRevisionQuery::STATUS_ANY            => pht('All'),
      DifferentialRevisionQuery::STATUS_OPEN           => pht('Open'),
      DifferentialRevisionQuery::STATUS_ACCEPTED       => pht('Accepted'),
      DifferentialRevisionQuery::STATUS_NEEDS_REVIEW   => pht('Needs Review'),
      DifferentialRevisionQuery::STATUS_NEEDS_REVISION => pht('Needs Revision'),
      DifferentialRevisionQuery::STATUS_CLOSED         => pht('Closed'),
      DifferentialRevisionQuery::STATUS_ABANDONED      => pht('Abandoned'),
    );
  }

  private function getOrderOptions() {
    return array(
      DifferentialRevisionQuery::ORDER_CREATED    => pht('Created'),
      DifferentialRevisionQuery::ORDER_MODIFIED   => pht('Updated'),
    );
  }

  protected function renderResultList(
    array $revisions,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $viewer = $this->requireViewer();
    $template = id(new DifferentialRevisionListView())
      ->setUser($viewer)
      ->setNoBox($this->isPanelContext());

    $views = array();
    if ($query->getQueryKey() == 'active') {
        $split = DifferentialRevisionQuery::splitResponsible(
          $revisions,
          $query->getParameter('responsiblePHIDs'));
        list($blocking, $active, $waiting) = $split;

      $views[] = id(clone $template)
        ->setHeader(pht('Blocking Others'))
        ->setNoDataString(
          pht('No revisions are blocked on your action.'))
        ->setHighlightAge(true)
        ->setRevisions($blocking)
        ->setHandles(array());

      $views[] = id(clone $template)
        ->setHeader(pht('Action Required'))
        ->setNoDataString(
          pht('No revisions require your action.'))
        ->setHighlightAge(true)
        ->setRevisions($active)
        ->setHandles(array());

      $views[] = id(clone $template)
        ->setHeader(pht('Waiting on Others'))
        ->setNoDataString(
          pht('You have no revisions waiting on others.'))
        ->setRevisions($waiting)
        ->setHandles(array());
    } else {
      $views[] = id(clone $template)
        ->setRevisions($revisions)
        ->setHandles(array());
    }

    $phids = array_mergev(mpull($views, 'getRequiredHandlePHIDs'));
    if ($phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    } else {
      $handles = array();
    }

    foreach ($views as $view) {
      $view->setHandles($handles);
    }

    if (count($views) == 1) {
      // Reduce this to a PHUIObjectItemListView so we can get the free
      // support from ApplicationSearch.
      $list = head($views)->render();
    } else {
      $list = $views;
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($list);

    return $result;
  }

}
