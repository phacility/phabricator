<?php

final class NuancePhabricatorFormSourceDefinition
  extends NuanceSourceDefinition {

  public function getName() {
    return pht('Phabricator Form');
  }

  public function getSourceDescription() {
    return pht('Create a web form that submits into a Nuance queue.');
  }

  public function getSourceTypeConstant() {
    return 'phabricator-form';
  }

  public function getSourceViewActions(AphrontRequest $request) {
    $actions = array();

    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('View Form'))
      ->setIcon('fa-align-justify')
      ->setHref($this->getActionURI());

    return $actions;
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


  public function handleActionRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    // TODO: As above, this would eventually be driven by custom logic.

    if ($request->isFormPost()) {
      $properties = array(
        'complaint' => (string)$request->getStr('complaint'),
      );

      $content_source = PhabricatorContentSource::newFromRequest($request);

      $requestor = NuanceRequestor::newFromPhabricatorUser(
        $viewer,
        $content_source);

      $item = $this->newItemFromProperties(
        $requestor,
        $properties,
        $content_source);

      $uri = $item->getURI();
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht('IMPORTANT: This is a very rough prototype.'))
      ->appendRemarkupInstructions(
        pht('Got a complaint? Complain here! We love complaints.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('complaint')
          ->setLabel(pht('Complaint')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Submit Complaint')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Complaint Form'))
      ->appendChild($form);

    return $box;
  }

  public function renderItemViewProperties(
    PhabricatorUser $viewer,
    NuanceItem $item,
    PHUIPropertyListView $view) {
    $this->renderItemCommonProperties($viewer, $item, $view);
  }

  public function renderItemEditProperties(
    PhabricatorUser $viewer,
    NuanceItem $item,
    PHUIPropertyListView $view) {
    $this->renderItemCommonProperties($viewer, $item, $view);
  }

  private function renderItemCommonProperties(
    PhabricatorUser $viewer,
    NuanceItem $item,
    PHUIPropertyListView $view) {

    $complaint = $item->getNuanceProperty('complaint');
    $complaint = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($complaint),
      'default',
      $viewer);

    $view->addSectionHeader(
      pht('Complaint'), 'fa-exclamation-circle');
    $view->addTextContent($complaint);
  }

}
