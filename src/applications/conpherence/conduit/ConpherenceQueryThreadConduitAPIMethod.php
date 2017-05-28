<?php

final class ConpherenceQueryThreadConduitAPIMethod
  extends ConpherenceConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conpherence.querythread';
  }

  public function getMethodDescription() {
    return pht(
      'Query for Conpherence threads for the logged in user. You can query '.
      'by IDs or PHIDs for specific Conpherence threads. Otherwise, specify '.
      'limit and offset to query the most recently updated Conpherences for '.
      'the logged in user.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional array<int>',
      'phids' => 'optional array<phids>',
      'limit' => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();
    $ids = $request->getValue('ids', array());
    $phids = $request->getValue('phids', array());
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $query = id(new ConpherenceThreadQuery())
      ->setViewer($user);

    if ($ids) {
      $conpherences = $query
        ->withIDs($ids)
        ->setLimit($limit)
        ->setOffset($offset)
        ->execute();
    } else if ($phids) {
      $conpherences = $query
        ->withPHIDs($phids)
        ->setLimit($limit)
        ->setOffset($offset)
        ->execute();
    } else {
      $participation = id(new ConpherenceParticipantQuery())
        ->withParticipantPHIDs(array($user->getPHID()))
        ->setLimit($limit)
        ->setOffset($offset)
        ->execute();
      $conpherence_phids = mpull($participation, 'getConpherencePHID');
      $query->withPHIDs($conpherence_phids);
      $conpherences = $query->execute();
      $conpherences = array_select_keys($conpherences, $conpherence_phids);
    }

    $data = array();
    foreach ($conpherences as $conpherence) {
      $id = $conpherence->getID();
      $data[$id] = array(
        'conpherenceID' => $id,
        'conpherencePHID' => $conpherence->getPHID(),
        'conpherenceTitle' => $conpherence->getTitle(),
        'messageCount' => $conpherence->getMessageCount(),
        'conpherenceURI' => $this->getConpherenceURI($conpherence),
      );
    }
    return $data;
  }

}
