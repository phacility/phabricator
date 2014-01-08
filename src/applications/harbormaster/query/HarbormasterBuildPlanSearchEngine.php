<?php

final class HarbormasterBuildPlanSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'status',
      $this->readListFromRequest($request, 'status'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new HarbormasterBuildPlanQuery());

    $status = $saved->getParameter('status', array());
    if ($status) {
      $query->withStatuses($status);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $status = $saved_query->getParameter('status', array());

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Status')
          ->addCheckbox(
            'status[]',
            HarbormasterBuildPlan::STATUS_ACTIVE,
            pht('Active'),
            in_array(HarbormasterBuildPlan::STATUS_ACTIVE, $status))
          ->addCheckbox(
            'status[]',
            HarbormasterBuildPlan::STATUS_DISABLED,
            pht('Disabled'),
            in_array(HarbormasterBuildPlan::STATUS_DISABLED, $status)));

  }

  protected function getURI($path) {
    return '/harbormaster/plan/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Plans'),
      'all' => pht('All Plans'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter(
          'status',
          array(
            HarbormasterBuildPlan::STATUS_ACTIVE,
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
