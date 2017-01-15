<?php

final class ConpherenceThreadDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Room');
  }

  public function getPlaceholderText() {
    return pht('Type a room title...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorConpherenceApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $rooms = id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withTitleNgrams($raw_query)
      ->needParticipants(true)
      ->execute();

    $results = array();
    foreach ($rooms as $room) {
      if (strlen($room->getTopic())) {
        $topic = $room->getTopic();
      } else {
        $topic = phutil_tag('em', array(), pht('No topic set'));
      }

      $token = id(new PhabricatorTypeaheadResult())
        ->setName($room->getTitle())
        ->setPHID($room->getPHID())
        ->addAttribute($topic);

      $results[] = $token;
    }

    return $results;
  }

}
