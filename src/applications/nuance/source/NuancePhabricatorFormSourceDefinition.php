<?php

final class NuancePhabricatorFormSourceDefinition
  extends NuanceSourceDefinition {

  public function getName() {
    return pht('Phabricator Form');
  }

  public function getSourceTypeConstant() {
    return 'phabricator-form';
  }

  public function updateItems() {
    return null;
  }

  protected function augmentEditForm(
    AphrontFormView $form,
    PhabricatorApplicationTransactionValidationException $ex = null) {

    /* TODO - add a box to allow for custom fields to be defined here, so that
     * these NuanceSource objects made from this definition can be used to
     * capture arbitrary data */

    return $form;
  }

  protected function buildTransactions(AphrontRequest $request) {
    $transactions = parent::buildTransactions($request);

    // TODO -- as above

    return $transactions;
  }

  public function renderView() {}

  public function renderListView() {}

}
