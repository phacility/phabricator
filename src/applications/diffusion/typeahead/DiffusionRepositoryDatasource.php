<?php

final class DiffusionRepositoryDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Repositories');
  }

  public function getPlaceholderText() {
    return pht('Type a repository name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $query = id(new PhabricatorRepositoryQuery())
      ->setOrder('name')
      ->withDatasourceQuery($raw_query);
    $repos = $this->executeQuery($query);

    $type_icon = id(new PhabricatorRepositoryRepositoryPHIDType())
      ->getTypeIcon();

    $image_sprite =
      "phabricator-search-icon phui-font-fa phui-icon-view {$type_icon}";

    $results = array();
    foreach ($repos as $repo) {
      $display_name = $repo->getMonogram().' '.$repo->getName();

      $name = $display_name;
      $slug = $repo->getRepositorySlug();
      if (strlen($slug)) {
        $name = "{$name} {$slug}";
      }

      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($name)
        ->setDisplayName($display_name)
        ->setURI($repo->getURI())
        ->setPHID($repo->getPHID())
        ->setPriorityString($repo->getMonogram())
        ->setPriorityType('repo')
        ->setImageSprite($image_sprite)
        ->setDisplayType(pht('Repository'));
    }

    return $results;
  }

}
