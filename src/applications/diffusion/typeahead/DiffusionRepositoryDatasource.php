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
    foreach ($repos as $repository) {
      $monogram = $repository->getMonogram();
      $name = $repository->getName();

      $display_name = "{$monogram} {$name}";

      $parts = array();
      $parts[] = $name;

      $slug = $repository->getRepositorySlug();
      if (strlen($slug)) {
        $parts[] = $slug;
      }

      $callsign = $repository->getCallsign();
      if ($callsign) {
        $parts[] = $callsign;
      }

      foreach ($repository->getAllMonograms() as $monogram) {
        $parts[] = $monogram;
      }

      $name = implode(' ', $parts);

      $vcs = $repository->getVersionControlSystem();
      $vcs_type = PhabricatorRepositoryType::getNameForRepositoryType($vcs);

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($name)
        ->setDisplayName($display_name)
        ->setURI($repository->getURI())
        ->setPHID($repository->getPHID())
        ->setPriorityString($repository->getMonogram())
        ->setPriorityType('repo')
        ->setImageSprite($image_sprite)
        ->setDisplayType(pht('Repository'))
        ->addAttribute($vcs_type);

      if (!$repository->isTracked()) {
        $result->setClosed(pht('Inactive'));
      }

      $results[] = $result;
    }

    return $results;
  }

}
