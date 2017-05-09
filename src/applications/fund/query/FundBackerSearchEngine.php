<?php

final class FundBackerSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $initiative;

  public function setInitiative(FundInitiative $initiative) {
    $this->initiative = $initiative;
    return $this;
  }

  public function getInitiative() {
    return $this->initiative;
  }

  public function getResultTypeDescription() {
    return pht('Fund Backers');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFundApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'backerPHIDs',
      $this->readUsersFromRequest($request, 'backers'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new FundBackerQuery());

    $query->withStatuses(array(FundBacker::STATUS_PURCHASED));

    if ($this->getInitiative()) {
      $query->withInitiativePHIDs(
        array(
          $this->getInitiative()->getPHID(),
        ));
    }

    $backer_phids = $saved->getParameter('backerPHIDs');
    if ($backer_phids) {
      $query->withBackerPHIDs($backer_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $backer_phids = $saved->getParameter('backerPHIDs', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Backers'))
          ->setName('backers')
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setValue($backer_phids));
  }

  protected function getURI($path) {
    if ($this->getInitiative()) {
      return '/fund/backers/'.$this->getInitiative()->getID().'/'.$path;
    } else {
      return '/fund/backers/'.$path;
    }
  }

  protected function getBuiltinQueryNames() {
    $names = array();
    $names['all'] = pht('All Backers');

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

  protected function getRequiredHandlePHIDsForResultList(
    array $backers,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($backers as $backer) {
      $phids[] = $backer->getBackerPHID();
      $phids[] = $backer->getInitiativePHID();
    }

    return $phids;
  }

  protected function renderResultList(
    array $backers,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($backers, 'FundBacker');

    $viewer = $this->requireViewer();

    $rows = array();
    foreach ($backers as $backer) {
      $rows[] = array(
        $handles[$backer->getInitiativePHID()]->renderLink(),
        $handles[$backer->getBackerPHID()]->renderLink(),
        $backer->getAmountAsCurrency()->formatForDisplay(),
        phabricator_datetime($backer->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No backers found.'))
      ->setHeaders(
        array(
          pht('Initiative'),
          pht('Backer'),
          pht('Amount'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide right',
          'right',
        ));

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($table);

    return $result;
  }

}
