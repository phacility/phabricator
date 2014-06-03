<?php

final class PhabricatorAppSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorApplicationApplications';
  }

  public function getPageSize(PhabricatorSavedQuery $saved) {
    return INF;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('name', $request->getStr('name'));

    $saved->setParameter(
      'installed',
      $this->readBoolFromRequest($request, 'installed'));
    $saved->setParameter(
      'beta',
      $this->readBoolFromRequest($request, 'beta'));
    $saved->setParameter(
      'firstParty',
      $this->readBoolFromRequest($request, 'firstParty'));
    $saved->setParameter(
      'launchable',
      $this->readBoolFromRequest($request, 'launchable'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorApplicationQuery())
      ->setOrder(PhabricatorApplicationQuery::ORDER_NAME)
      ->withUnlisted(false);

    $name = $saved->getParameter('name');
    if (strlen($name)) {
      $query->withNameContains($name);
    }

    $installed = $saved->getParameter('installed');
    if ($installed !== null) {
      $query->withInstalled($installed);
    }

    $beta = $saved->getParameter('beta');
    if ($beta !== null) {
      $query->withBeta($beta);
    }

    $first_party = $saved->getParameter('firstParty');
    if ($first_party !== null) {
      $query->withFirstParty($first_party);
    }

    $launchable = $saved->getParameter('launchable');
    if ($launchable !== null) {
      $query->withLaunchable($launchable);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name Contains'))
          ->setName('name')
          ->setValue($saved->getParameter('name')))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Installed'))
          ->setName('installed')
          ->setValue($this->getBoolFromQuery($saved, 'installed'))
          ->setOptions(
            array(
              '' => pht('Show All Applications'),
              'true' => pht('Show Installed Applications'),
              'false' => pht('Show Uninstalled Applications'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Beta'))
          ->setName('beta')
          ->setValue($this->getBoolFromQuery($saved, 'beta'))
          ->setOptions(
            array(
              '' => pht('Show All Applications'),
              'true' => pht('Show Beta Applications'),
              'false' => pht('Show Released Applications'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Provenance'))
          ->setName('firstParty')
          ->setValue($this->getBoolFromQuery($saved, 'firstParty'))
          ->setOptions(
            array(
              '' => pht('Show All Applications'),
              'true' => pht('Show First-Party Applications'),
              'false' => pht('Show Third-Party Applications'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Launchable'))
          ->setName('launchable')
          ->setValue($this->getBoolFromQuery($saved, 'launchable'))
          ->setOptions(
            array(
              '' => pht('Show All Applications'),
              'true' => pht('Show Launchable Applications'),
              'false' => pht('Show Non-Launchable Applications'),
            )));

  }

  protected function getURI($path) {
    return '/applications/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'launcher' => pht('Launcher'),
      'all' => pht('All Applications'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'launcher':
        return $query
          ->setParameter('installed', true)
          ->setParameter('launchable', true);
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $all_applications,
    PhabricatorSavedQuery $query,
    array $handle) {
    assert_instances_of($all_applications, 'PhabricatorApplication');

    $all_applications = msort($all_applications, 'getName');

    if ($query->getQueryKey() == 'launcher') {
      $groups = mgroup($all_applications, 'getApplicationGroup');
    } else {
      $groups = array($all_applications);
    }

    $group_names = PhabricatorApplication::getApplicationGroups();
    $groups = array_select_keys($groups, array_keys($group_names)) + $groups;

    $results = array();
    foreach ($groups as $group => $applications) {
      if (count($groups) > 1) {
        $results[] = phutil_tag(
          'h1',
          array(
            'class' => 'launcher-header',
          ),
          idx($group_names, $group, $group));
      }

      $list = new PHUIObjectItemListView();
      $list->addClass('phui-object-item-launcher-list');

      foreach ($applications as $application) {
        $icon = $application->getIconName();
        if (!$icon) {
          $icon = 'application';
        }

        // TODO: This sheet doesn't work the same way other sheets do so it
        // ends up with the wrong classes if we try to use PHUIIconView. This
        // is probably all changing in the redesign anyway.

        $icon_view = javelin_tag(
          'span',
          array(
            'class' => 'phui-icon-view '.
                       'sprite-apps-large apps-'.$icon.'-dark-large',
            'aural' => false,
          ),
          '');

        $description = phutil_tag(
          'div',
          array(
            'style' => 'white-space: nowrap; '.
                       'overflow: hidden; '.
                       'text-overflow: ellipsis;',
          ),
          $application->getShortDescription());

        $item = id(new PHUIObjectItemView())
          ->setHeader($application->getName())
          ->setImageIcon($icon_view)
          ->addAttribute($description)
          ->addAction(
            id(new PHUIListItemView())
              ->setName(pht('Help/Options'))
              ->setIcon('fa-cog')
              ->setHref('/applications/view/'.get_class($application).'/'));

        if ($application->getBaseURI()) {
          $item->setHref($application->getBaseURI());
        }

        if (!$application->isInstalled()) {
          $item->addIcon('fa-times', pht('Uninstalled'));
        }

        if ($application->isBeta()) {
          $item->addIcon('fa-star-half-o grey', pht('Beta'));
        }

        if (!$application->isFirstParty()) {
          $item->addIcon('fa-puzzle-piece', pht('Extension'));
        }

        $list->addItem($item);
      }

      $results[] = $list;
    }

    return $results;
  }

}
