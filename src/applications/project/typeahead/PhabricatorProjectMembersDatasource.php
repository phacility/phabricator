<?php

final class PhabricatorProjectMembersDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type members(<project>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $pattern = $raw_query;
    if (self::isFunctionToken($raw_query)) {
      $function = $this->parseFunction($raw_query, $allow_partial = true);
      if ($function) {
        $pattern = head($function['argv']);
      }
    }

    // Allow users to type "#qa" or "qa" to find "Quality Assurance".
    $pattern = ltrim($pattern, '#');
    $tokens = self::tokenizeString($pattern);

    $query = $this->newQuery();
    if ($tokens) {
      $query->withNameTokens($tokens);
    }
    $projects = $this->executeQuery($query);

    $results = array();
    foreach ($projects as $project) {
      $results[] = $this->buildProjectResult($project);
    }

    return $results;
  }

  protected function canEvaluateFunction($function) {
    return ($function == 'members');
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($this->getViewer())
      ->needMembers(true)
      ->withPHIDs($phids)
      ->execute();

    $results = array();
    foreach ($projects as $project) {
      foreach ($project->getMemberPHIDs() as $phid) {
        $results[$phid] = $phid;
      }
    }

    return array_values($results);
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $projects = $this->newQuery()
      ->withPHIDs($phids)
      ->execute();
    $projects = mpull($projects, null, 'getPHID');

    $tokens = array();
    foreach ($phids as $phid) {
      $project = idx($projects, $phid);
      if ($project) {
        $result = $this->buildProjectResult($project);
        $tokens[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
          $result);
      } else {
        $tokens[] = $this->newInvalidToken(pht('Members: Invalid Project'));
      }
    }

    return $tokens;
  }

  private function newQuery() {
    return id(new PhabricatorProjectQuery())
      ->setViewer($this->getViewer())
      ->needImages(true)
      ->needSlugs(true);
  }

  private function buildProjectResult(PhabricatorProject $project) {
    $closed = null;
    if ($project->isArchived()) {
      $closed = pht('Archived');
    }

    $all_strings = mpull($project->getSlugs(), 'getSlug');
    $all_strings[] = 'members';
    $all_strings[] = $project->getName();
    $all_strings = implode(' ', $all_strings);

    return $this->newFunctionResult()
      ->setName($all_strings)
      ->setDisplayName(pht('Members: %s', $project->getName()))
      ->setURI('/tag/'.$project->getPrimarySlug().'/')
      ->setPHID('members('.$project->getPHID().')')
      ->setIcon('fa-users')
      ->setClosed($closed);
  }

}
