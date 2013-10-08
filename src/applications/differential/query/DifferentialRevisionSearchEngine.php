<?php

final class DifferentialRevisionSearchEngine
  extends PhabricatorApplicationSearchEngine {

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
          PhabricatorProjectPHIDTypeProject::TYPECONST,
        )));

    $saved->setParameter(
      'subscriberPHIDs',
      $this->readUsersFromRequest($request, 'subscribers'));

    $saved->setParameter(
      'repositoryPHIDs',
      $request->getArr('repositories'));

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
      ->needRelationships(true);

    $responsible_phids = $saved->getParameter('responsiblePHIDs', array());
    if ($responsible_phids) {
      $query->withResponsibleUsers($responsible_phids);
    }

    $author_phids = $saved->getParameter('authorPHIDs', array());
    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $reviewer_phids = $saved->getParameter('reviewerPHIDs', array());
    if ($reviewer_phids) {
      $query->withReviewers($reviewer_phids);
    }

    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());
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

    $all_phids = array_mergev(
      array(
        $responsible_phids,
        $author_phids,
        $reviewer_phids,
        $subscriber_phids,
        $repository_phids,
      ));

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($all_phids)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Responsible Users'))
          ->setName('responsibles')
          ->setDatasource('/typeahead/common/accounts/')
          ->setValue(array_select_keys($handles, $responsible_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Authors'))
          ->setName('authors')
          ->setDatasource('/typeahead/common/accounts/')
          ->setValue(array_select_keys($handles, $author_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Reviewers'))
          ->setName('reviewers')
          ->setDatasource('/typeahead/common/accountsorprojects/')
          ->setValue(array_select_keys($handles, $reviewer_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Subscribers'))
          ->setName('subscribers')
          ->setDatasource('/typeahead/common/allmailable/')
          ->setValue(array_select_keys($handles, $subscriber_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Repositories'))
          ->setName('repositories')
          ->setDatasource('/typeahead/common/repositories/')
          ->setValue(array_select_keys($handles, $repository_phids)))
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

  public function getBuiltinQueryNames() {
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
      DifferentialRevisionQuery::STATUS_ANY       => pht('All'),
      DifferentialRevisionQuery::STATUS_OPEN      => pht('Open'),
      DifferentialRevisionQuery::STATUS_CLOSED    => pht('Closed'),
      DifferentialRevisionQuery::STATUS_ABANDONED => pht('Abandoned'),
    );
  }

  private function getOrderOptions() {
    return array(
      DifferentialRevisionQuery::ORDER_CREATED    => pht('Created'),
      DifferentialRevisionQuery::ORDER_MODIFIED   => pht('Updated'),
    );
  }

}
