<?php

final class ReleephBranchSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $product;

  public function getResultTypeDescription() {
    return pht('Releeph Branches');
  }

  public function getApplicationClassName() {
    return 'PhabricatorReleephApplication';
  }

  public function setProduct(ReleephProject $product) {
    $this->product = $product;
    return $this;
  }

  public function getProduct() {
    return $this->product;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('active', $request->getStr('active'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ReleephBranchQuery())
      ->needCutPointCommits(true)
      ->withProductPHIDs(array($this->getProduct()->getPHID()));

    $active = $saved->getParameter('active');
    $value = idx($this->getActiveValues(), $active);
    if ($value !== null) {
      $query->withStatus($value);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('active')
        ->setLabel(pht('Show Branches'))
        ->setValue($saved_query->getParameter('active'))
        ->setOptions($this->getActiveOptions()));
  }

  protected function getURI($path) {
    return '/releeph/product/'.$this->getProduct()->getID().'/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open'),
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'open':
        return $query
          ->setParameter('active', 'open');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getActiveOptions() {
    return array(
      'open' => pht('Open Branches'),
      'all' => pht('Open and Closed Branches'),
    );
  }

  private function getActiveValues() {
    return array(
      'open' => ReleephBranchQuery::STATUS_OPEN,
      'all' => ReleephBranchQuery::STATUS_ALL,
    );
  }

}
