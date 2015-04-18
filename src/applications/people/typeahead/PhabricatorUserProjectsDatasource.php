<?php

final class PhabricatorUserProjectsDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type projects(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'projects' => array(
        'name' => pht("Find results in any of a user's projects."),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-briefcase')
        ->setPHID('projects('.$result->getPHID().')')
        ->setDisplayName(pht('Projects: %s', $result->getDisplayName()))
        ->setName($result->getName().' projects');
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $projects = id(new PhabricatorPeopleQuery())
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

    $tokens = $this->renderTokens($phids);
    foreach ($tokens as $token) {
      if ($token->isInvalid()) {
        $token
          ->setValue(pht('Projects: Invalid User'));
      } else {
        $token
          ->setIcon('fa-users')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('projects('.$token->getKey().')')
          ->setValue(pht('Projects: %s', $token->getValue()));
      }
    }

    return $tokens;
  }

}
