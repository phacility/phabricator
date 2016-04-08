<?php

final class PhabricatorBadgesSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Badge');
  }

  public function getApplicationClassName() {
    return 'PhabricatorBadgesApplication';
  }

  public function newQuery() {
    return new PhabricatorBadgesQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name')
        ->setDescription(pht('Search for badges by name substring.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('qualities')
        ->setLabel(pht('Quality'))
        ->setOptions(PhabricatorBadgesQuality::getDropdownQualityMap()),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setOptions(
          id(new PhabricatorBadgesBadge())
            ->getStatusNameMap()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    if ($map['qualities']) {
      $query->withQualities($map['qualities']);
    }

    if ($map['name'] !== null) {
      $query->withNameNgrams($map['name']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/badges/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['open'] = pht('Active Badges');
    $names['all'] = pht('All Badges');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'open':
        return $query->setParameter(
          'statuses',
          array(
            PhabricatorBadgesBadge::STATUS_ACTIVE,
          ));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $badges,
    PhabricatorSavedQuery $query) {

    $phids = array();

    return $phids;
  }

  protected function renderResultList(
    array $badges,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($badges, 'PhabricatorBadgesBadge');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView());
    foreach ($badges as $badge) {
      $quality_name = PhabricatorBadgesQuality::getQualityName(
        $badge->getQuality());

      $mini_badge = id(new PHUIBadgeMiniView())
        ->setHeader($badge->getName())
        ->setIcon($badge->getIcon())
        ->setQuality($badge->getQuality());

      $item = id(new PHUIObjectItemView())
        ->setHeader($badge->getName())
        ->setBadge($mini_badge)
        ->setHref('/badges/view/'.$badge->getID().'/')
        ->addAttribute($quality_name)
        ->addAttribute($badge->getFlavor());

      if ($badge->isArchived()) {
        $item->setDisabled(true);
        $item->addIcon('fa-ban', pht('Archived'));
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No badges found.'));

    return $result;

  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Badge'))
      ->setHref('/badges/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Badges let you award and distinguish special users '.
          'throughout your instance.'))
      ->addAction($create_button);

      return $view;
  }

}
