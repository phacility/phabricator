<?php

final class HeraldWebhookSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Webhooks');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHeraldApplication';
  }

  public function newQuery() {
    return new HeraldWebhookQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setDescription(
          pht('Search for archived or active pastes.'))
        ->setOptions(HeraldWebhook::getStatusDisplayNameMap()),
    );
  }

  protected function getURI($path) {
    return '/herald/webhook/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['active'] = pht('Active');
    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query->setParameter(
          'statuses',
          array(
            HeraldWebhook::HOOKSTATUS_FIREHOSE,
            HeraldWebhook::HOOKSTATUS_ENABLED,
          ));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $hooks,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($hooks, 'HeraldWebhook');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);
    foreach ($hooks as $hook) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Webhook %d', $hook->getID()))
        ->setHeader($hook->getName())
        ->setHref($hook->getURI())
        ->addAttribute($hook->getWebhookURI());

      $item->addIcon($hook->getStatusIcon(), $hook->getStatusDisplayName());

      if ($hook->isDisabled()) {
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No webhooks found.'));
  }

}
