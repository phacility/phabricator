<?php

final class HarbormasterQueryBuildsConduitAPIMethod
  extends HarbormasterConduitAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.querybuilds';
  }

  public function getMethodDescription() {
    return pht('Query Harbormaster builds.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht('Use %s instead.', 'harbormaster.build.search');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'buildStatuses' => 'optional list<string>',
      'buildablePHIDs' => 'optional list<phid>',
      'buildPlanPHIDs' => 'optional list<phid>',
    ) + self::getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'wild';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $call = new ConduitCall(
      'harbormaster.build.search',
      array_filter(array(
        'constraints' => array_filter(array(
          'ids' => $request->getValue('ids'),
          'phids' => $request->getValue('phids'),
          'statuses' => $request->getValue('buildStatuses'),
          'buildables' => $request->getValue('buildablePHIDs'),
          'plans' => $request->getValue('buildPlanPHIDs'),
        )),
        'attachments' => array(
          'querybuilds' => true,
        ),
        'limit' => $request->getValue('limit'),
        'before' => $request->getValue('before'),
        'after' => $request->getValue('after'),
      )));

    $subsumption = $call->setUser($viewer)
      ->execute();

    $data = array();
    foreach ($subsumption['data'] as $build_data) {
      $querybuilds = idxv(
        $build_data,
        array('attachments', 'querybuilds'),
        array());
      $fields = idx($build_data, 'fields', array());
      unset($build_data['fields']);
      unset($build_data['attachments']);

      // To retain backward compatibility, remove newer keys from the
      // result array.
      $fields['buildStatus'] = array_select_keys(
        $fields['buildStatus'],
        array(
          'value',
          'name',
        ));

      $data[] = array_mergev(array($build_data, $querybuilds, $fields));
    }

    $subsumption['data'] = $data;

    return $subsumption;
  }

}
