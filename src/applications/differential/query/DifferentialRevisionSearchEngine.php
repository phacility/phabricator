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
      $request->getArr('responsiblePHIDs'));

    $saved->setParameter(
      'authorPHIDs',
      $request->getArr('authorPHIDs'));

    $saved->setParameter(
      'reviewerPHIDs',
      $request->getArr('reviewerPHIDs'));

    $saved->setParameter(
      'subscriberPHIDs',
      $request->getArr('subscriberPHIDs'));

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
    $only_draft = $saved->getParameter('draft', false);

    $all_phids = array_mergev(
      array(
        $responsible_phids,
        $author_phids,
        $reviewer_phids,
        $subscriber_phids,
      ));

    $handles = id(new PhabricatorObjectHandleData($all_phids))
      ->setViewer($this->requireViewer())
      ->loadHandles();

    $tokens = mpull($handles, 'getFullName', 'getPHID');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Responsible Users'))
          ->setName('responsiblePHIDs')
          ->setDatasource('/typeahead/common/users/')
          ->setValue(array_select_keys($tokens, $responsible_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Authors'))
          ->setName('authorPHIDs')
          ->setDatasource('/typeahead/common/authors/')
          ->setValue(array_select_keys($tokens, $author_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Reviewers'))
          ->setName('reviewerPHIDs')
          ->setDatasource('/typeahead/common/users/')
          ->setValue(array_select_keys($tokens, $reviewer_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Subscribers'))
          ->setName('subscriberPHIDs')
          ->setDatasource('/typeahead/common/mailable/')
          ->setValue(array_select_keys($tokens, $subscriber_phids)))
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
