<?php

class PhabricatorFeedIDQuery
  extends PhabricatorFeedQuery {

  // Hardcode table value to 'story' because in feed.query_id we
  // are paging off of the ID value instead of chronologicalKey.
  // The story data table has an ID column where the story
  // references table does not.
  public function getOrderableColumns() {
    return array(
      'key' => array(
        'table'  => 'story',
        'column' => 'id',
        'type'   => 'int',
        'unique' => true,
      ),
    );
  }

  public function applyExternalCursorConstraintsToQuery(
    PhabricatorCursorPagedPolicyAwareQuery $subquery,
    $cursor) {
    $subquery->withIDs(array($cursor));
  }

  public function newExternalCursorStringForResult($object) {
    return $object->getStoryData()->getID();
  }

  public function newPagingMapFromPartialObject($object) {
    return array('key' => $object['id'],);
  }
}

