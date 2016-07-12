<?php

final class PhabricatorProjectMembersDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Members');
  }

  public function getPlaceholderText() {
    return pht('Type members(<project>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'members' => array(
        'name' => pht('Members: ...'),
        'arguments' => pht('project'),
        'summary' => pht('Find results for members of a project.'),
        'description' => pht(
          'This function allows you to find results for any of the members '.
          'of a project:'.
          "\n\n".
          '> members(frontend)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-users')
        ->setColor(null)
        ->setPHID('members('.$result->getPHID().')')
        ->setDisplayName(pht('Members: %s', $result->getDisplayName()))
        ->setName($result->getName().' members')
        ->resetAttributes()
        ->addAttribute(pht('Function'))
        ->addAttribute(pht('Select project members.'));
    }

    return $results;
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

    $tokens = $this->renderTokens($phids);
    foreach ($tokens as $token) {
      // Remove any project color on this token.
      $token->setColor(null);

      if ($token->isInvalid()) {
        $token
          ->setValue(pht('Members: Invalid Project'));
      } else {
        $token
          ->setIcon('fa-users')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('members('.$token->getKey().')')
          ->setValue(pht('Members: %s', $token->getValue()));
      }
    }

    return $tokens;
  }

}
