<?php

abstract class PhabricatorConfigServicesController
  extends PhabricatorConfigController {

  public function newNavigation($select_filter) {
    $services_uri = $this->getApplicationURI();

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($services_uri));

    $nav->addLabel(pht('Databases'));

    $nav->newLink('database-servers')
      ->setName(pht('Database Servers'))
      ->setIcon('fa-database')
      ->setHref(urisprintf('%s%s/', $services_uri, 'cluster/databases'));

    $nav->newLink('schemata')
      ->setName(pht('Database Schemata'))
      ->setIcon('fa-table')
      ->setHref(urisprintf('%s%s/', $services_uri, 'database'));

    $nav->newLink('schemata-issues')
      ->setName(pht('Schemata Issues'))
      ->setIcon('fa-exclamation-circle')
      ->setHref(urisprintf('%s%s/', $services_uri, 'dbissue'));


    $nav->addLabel(pht('Cache'));

    $nav->newLink('cache')
      ->setName(pht('Cache Status'))
      ->setIcon('fa-archive')
      ->setHref(urisprintf('%s%s/', $services_uri, 'cache'));

    $nav->addLabel(pht('Other Services'));

    $nav->newLink('notification-servers')
      ->setName(pht('Notification Servers'))
      ->setIcon('fa-bell-o')
      ->setHref(urisprintf('%s%s/', $services_uri, 'cluster/notifications'));

    $nav->newLink('repository-servers')
      ->setName(pht('Repository Servers'))
      ->setIcon('fa-code')
      ->setHref(urisprintf('%s%s/', $services_uri, 'cluster/repositories'));

    $nav->newLink('search-servers')
      ->setName(pht('Search Servers'))
      ->setIcon('fa-search')
      ->setHref(urisprintf('%s%s/', $services_uri, 'cluster/search'));

    if ($select_filter) {
      $nav->selectFilter($select_filter);
    }

    return $nav;
  }

  public function newCrumbs() {
    $services_uri = $this->getApplicationURI('cluster/databases/');

    return $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Services'))
      ->setBorder(true);
  }

}
