<?php

final class PhabricatorAppSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getPageSize(PhabricatorSavedQuery $saved) {
    return INF;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('name', $request->getStr('name'));

    $saved->setParameter('installed', $this->readBool($request, 'installed'));
    $saved->setParameter('beta', $this->readBool($request, 'beta'));
    $saved->setParameter('firstParty', $this->readBool($request, 'firstParty'));

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
          ->setValue($this->getBool($saved, 'installed'))
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
          ->setValue($this->getBool($saved, 'beta'))
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
          ->setValue($this->getBool($saved, 'firstParty'))
          ->setOptions(
            array(
              '' => pht('Show All Applications'),
              'true' => pht('Show First-Party Applications'),
              'false' => pht('Show Third-Party Applications'),
            )));

  }

  protected function getURI($path) {
    return '/applications/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Applications'),
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

  private function readBool(AphrontRequest $request, $key) {
    if (!strlen($request->getStr($key))) {
      return null;
    }
    return $request->getBool($key);
  }

  private function getBool(PhabricatorSavedQuery $query, $key) {
    $value = $query->getParameter($key);
    if ($value === null) {
      return $value;
    }
    return $value ? 'true' : 'false';
  }

}
