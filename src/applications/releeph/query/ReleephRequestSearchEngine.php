<?php

final class ReleephRequestSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $branch;
  private $baseURI;

  public function getResultTypeDescription() {
    return pht('Releeph Pull Requests');
  }

  public function getApplicationClassName() {
    return 'PhabricatorReleephApplication';
  }

  public function setBranch(ReleephBranch $branch) {
    $this->branch = $branch;
    return $this;
  }

  public function getBranch() {
    return $this->branch;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('status', $request->getStr('status'));
    $saved->setParameter('severity', $request->getStr('severity'));
    $saved->setParameter(
      'requestorPHIDs',
      $this->readUsersFromRequest($request, 'requestors'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ReleephRequestQuery())
      ->withBranchIDs(array($this->getBranch()->getID()));

    $status = $saved->getParameter('status');
    $status = idx($this->getStatusValues(), $status);
    if ($status) {
      $query->withStatus($status);
    }

    $severity = $saved->getParameter('severity');
    if ($severity) {
      $query->withSeverities(array($severity));
    }

    $requestor_phids = $saved->getParameter('requestorPHIDs');
    if ($requestor_phids) {
      $query->withRequestorPHIDs($requestor_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $requestor_phids = $saved_query->getParameter('requestorPHIDs', array());

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('status')
          ->setLabel(pht('Status'))
          ->setValue($saved_query->getParameter('status'))
          ->setOptions($this->getStatusOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('severity')
          ->setLabel(pht('Severity'))
          ->setValue($saved_query->getParameter('severity'))
          ->setOptions($this->getSeverityOptions()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('requestors')
          ->setLabel(pht('Requestors'))
          ->setValue($requestor_phids));
  }

  protected function getURI($path) {
    return $this->baseURI.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open Requests'),
      'all' => pht('All Requests'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['requested'] = pht('Requested');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'open':
        return $query->setParameter('status', 'open');
      case 'all':
        return $query;
      case 'requested':
        return $query->setParameter(
          'requestorPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      ''              => pht('(All Requests)'),
      'open'          => pht('Open Requests'),
      'requested'     => pht('Pull Requested'),
      'needs-pull'    => pht('Needs Pull'),
      'rejected'      => pht('Rejected'),
      'abandoned'     => pht('Abandoned'),
      'pulled'        => pht('Pulled'),
      'needs-revert'  => pht('Needs Revert'),
      'reverted'      => pht('Reverted'),
    );
  }

  private function getStatusValues() {
    return array(
      'open'          => ReleephRequestQuery::STATUS_OPEN,
      'requested'     => ReleephRequestQuery::STATUS_REQUESTED,
      'needs-pull'    => ReleephRequestQuery::STATUS_NEEDS_PULL,
      'rejected'      => ReleephRequestQuery::STATUS_REJECTED,
      'abandoned'     => ReleephRequestQuery::STATUS_ABANDONED,
      'pulled'        => ReleephRequestQuery::STATUS_PULLED,
      'needs-revert'  => ReleephRequestQuery::STATUS_NEEDS_REVERT,
      'reverted'      => ReleephRequestQuery::STATUS_REVERTED,
    );
  }

  private function getSeverityOptions() {
    if (ReleephDefaultFieldSelector::isFacebook()) {
      return array(
        '' => pht('(All Severities)'),
        11 => 'HOTFIX',
        12 => 'PIGGYBACK',
        13 => 'RELEASE',
        14 => 'DAILY',
        15 => 'PARKING',
      );
    } else {
      return array(
        '' => pht('(All Severities)'),
        ReleephSeverityFieldSpecification::HOTFIX => pht('Hotfix'),
        ReleephSeverityFieldSpecification::RELEASE => pht('Release'),
      );
    }
  }

}
