<?php

final class PhrequentTrackingConduitAPIMethod
  extends PhrequentConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phrequent.tracking';
  }

  public function getMethodDescription() {
    return pht(
      'Returns current objects being tracked in Phrequent.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();

    $times = id(new PhrequentUserTimeQuery())
      ->setViewer($user)
      ->needPreemptingEvents(true)
      ->withEnded(PhrequentUserTimeQuery::ENDED_NO)
      ->withUserPHIDs(array($user->getPHID()))
      ->execute();

    $now = time();

    $results = id(new PhrequentTimeBlock($times))
      ->getCurrentWorkStack($now);

    return array('data' => $results);
  }

}
