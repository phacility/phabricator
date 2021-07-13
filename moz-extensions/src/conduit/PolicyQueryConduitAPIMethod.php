<?php
class PolicyQueryConduitAPIMethod extends ConduitAPIMethod {
  public function getAPIMethodName() {
    return 'policy.query';
  }

  public function getMethodDescription() {
    return pht('Find information about custom policies.');
  }

  protected function defineParamTypes() {
    return array(
      'phids'  => 'required list<phid>',
      'limit'  => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'dict<string, wild>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $pager = $this->newPager($request);

    $phids = $request->getValue('phids');
    if (!$phids) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(pht("PHIDs required"));
    }

    $query = id(new PhabricatorPolicyQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($phids);
    $policies = $query->executeWithCursorPager($pager);

    if (!$policies) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(
          pht("Unknown policies: %s", implode(', ', $phids)));
    }

    $results = array();
    foreach ($policies as $phid => $policy) {
      $type = $policy->getType();
      $data = array(
        'phid'      => $phid,
        'type'      => $type,
        'name'      => $policy->getName(),
        'shortName' => $policy->getShortName(),
        'fullName'  => $policy->getFullName(),
        'href'      => $policy->getHref(),
        'workflow'  => $policy->getWorkflow(),
        'icon'      => $policy->getIcon(),
      );
      if ($type === PhabricatorPolicyType::TYPE_CUSTOM) {
        $data['default'] = $policy->getDefaultAction();
        $data['rules']   = $policy->getRules();
      }
      $results[] = $data;
    }

    $result = array(
      'data' => $results,
    );

    return $this->addPagerResults($result, $pager);
  }
}

