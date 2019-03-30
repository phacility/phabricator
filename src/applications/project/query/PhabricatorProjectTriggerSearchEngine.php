<?php

final class PhabricatorProjectTriggerSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Triggers');
  }

  public function getApplicationClassName() {
    return 'PhabricatorProjectApplication';
  }

  public function newQuery() {
    return id(new PhabricatorProjectTriggerQuery())
      ->needUsage(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Active'))
        ->setKey('isActive')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Active Triggers'),
          pht('Show Only Inactive Triggers')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['isActive'] !== null) {
      if ($map['isActive']) {
        $query->withActiveColumnCountBetween(1, null);
      } else {
        $query->withActiveColumnCountBetween(null, 0);
      }
    }

    return $query;
  }

  protected function getURI($path) {
    return '/project/trigger/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['active'] = pht('Active Triggers');
    $names['all'] = pht('All Triggers');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('isActive', true);
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $triggers,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($triggers, 'PhabricatorProjectTrigger');
    $viewer = $this->requireViewer();

    $example_phids = array();
    foreach ($triggers as $trigger) {
      $example_phid = $trigger->getUsage()->getExamplePHID();
      if ($example_phid) {
        $example_phids[] = $example_phid;
      }
    }

    $handles = $viewer->loadHandles($example_phids);

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);
    foreach ($triggers as $trigger) {
      $usage = $trigger->getUsage();

      $column_handle = null;
      $have_column = false;
      $example_phid = $usage->getExamplePHID();
      if ($example_phid) {
        $column_handle = $handles[$example_phid];
        if ($column_handle->isComplete()) {
          if (!$column_handle->getPolicyFiltered()) {
            $have_column = true;
          }
        }
      }

      $column_count = $usage->getColumnCount();
      $active_count = $usage->getActiveColumnCount();

      if ($have_column) {
        if ($active_count > 1) {
          $usage_description = pht(
            'Used on %s and %s other active column(s).',
            $column_handle->renderLink(),
            new PhutilNumber($active_count - 1));
        } else if ($column_count > 1) {
          $usage_description = pht(
            'Used on %s and %s other column(s).',
            $column_handle->renderLink(),
            new PhutilNumber($column_count - 1));
        } else {
          $usage_description = pht(
            'Used on %s.',
            $column_handle->renderLink());
        }
      } else {
        if ($active_count) {
          $usage_description = pht(
            'Used on %s active column(s).',
            new PhutilNumber($active_count));
        } else if ($column_count) {
          $usage_description = pht(
            'Used on %s column(s).',
            new PhutilNumber($column_count));
        } else {
          $usage_description = pht(
            'Unused trigger.');
        }
      }

      $item = id(new PHUIObjectItemView())
        ->setObjectName($trigger->getObjectName())
        ->setHeader($trigger->getDisplayName())
        ->setHref($trigger->getURI())
        ->addAttribute($usage_description)
        ->setDisabled(!$active_count);

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No triggers found.'));
  }

}
