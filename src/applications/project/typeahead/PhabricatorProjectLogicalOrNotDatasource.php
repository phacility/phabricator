<?php

final class PhabricatorProjectLogicalOrNotDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type any(<project>) or not(<project>)...');
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
      'any' => array(
        'name' => pht('Find results in any of several projects.'),
      ),
      'not' => array(
        'name' => pht('Find results not in specific projects.'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    $function = $this->getCurrentFunction();
    $return_any = ($function !== 'not');
    $return_not = ($function !== 'any');

    $return = array();
    foreach ($results as $result) {
      $result
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk');

      if ($return_any) {
        $return[] = id(clone $result)
          ->setPHID('any('.$result->getPHID().')')
          ->setDisplayName(pht('In Any: %s', $result->getDisplayName()))
          ->setName($result->getName().' any');
      }

      if ($return_not) {
        $return[] = id(clone $result)
          ->setPHID('not('.$result->getPHID().')')
          ->setDisplayName(pht('Not In: %s', $result->getDisplayName()))
          ->setName($result->getName().' not');
      }
    }

    return $return;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $operator = array(
      'any' => PhabricatorQueryConstraint::OPERATOR_OR,
      'not' => PhabricatorQueryConstraint::OPERATOR_NOT,
    );

    $results = array();
    foreach ($phids as $phid) {
      $results[] = new PhabricatorQueryConstraint(
        $operator[$function],
        $phid);
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $tokens = $this->renderTokens($phids);
    foreach ($tokens as $token) {
      if ($token->isInvalid()) {
        if ($function == 'any') {
          $token->setValue(pht('In Any: Invalid Project'));
        } else {
          $token->setValue(pht('Not In: Invalid Project'));
        }
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION);

        if ($function == 'any') {
          $token
            ->setKey('any('.$token->getKey().')')
            ->setValue(pht('In Any: %s', $token->getValue()));
        } else {
          $token
            ->setKey('not('.$token->getKey().')')
            ->setValue(pht('Not In: %s', $token->getValue()));
        }
      }
    }

    return $tokens;
  }

}
