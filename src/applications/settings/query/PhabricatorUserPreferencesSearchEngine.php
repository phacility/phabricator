<?php

final class PhabricatorUserPreferencesSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('User Preferences');
  }

  public function getApplicationClassName() {
    return 'PhabricatorSettingApplication';
  }

  public function newQuery() {
    return id(new PhabricatorUserPreferencesQuery())
      ->withHasUserPHID(false);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    return '/settings/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Settings'),
    );

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

  protected function renderResultList(
    array $settings,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($settings, 'PhabricatorUserPreferences');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);
    foreach ($settings as $setting) {

      $icon = id(new PHUIIconView())
        ->setIcon('fa-globe')
        ->setBackground('bg-sky');

      $item = id(new PHUIObjectItemView())
        ->setHeader($setting->getDisplayName())
        ->setHref($setting->getEditURI())
        ->setImageIcon($icon)
        ->addAttribute(pht('Edit global default settings for all users.'));

      $list->addItem($item);
    }

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Personal Account Settings'))
        ->addAttribute(pht('Edit settings for your personal account.'))
        ->setImageURI($viewer->getProfileImageURI())
        ->setHref('/settings/user/'.$viewer->getUsername().'/'));

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list);
  }

}
