<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_find_Method extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'differential.query'.";
  }

  public function getMethodDescription() {
    return "Query Differential revisions which match certain criteria.";
  }

  public function defineParamTypes() {
    $types = array(
      DifferentialRevisionListData::QUERY_OPEN_OWNED,
      DifferentialRevisionListData::QUERY_COMMITTABLE,
      DifferentialRevisionListData::QUERY_REVISION_IDS,
      DifferentialRevisionListData::QUERY_PHIDS,
    );

    $types = implode(', ', $types);

    return array(
      'query' => 'required enum<'.$types.'>',
      'guids' => 'required nonempty list<guids>',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = $request->getValue('query');
    $guids = $request->getValue('guids');

    $results = array();
    if (!$guids) {
      return $results;
    }

    $revisions = id(new DifferentialRevisionListData(
      $query,
      (array)$guids))
      ->loadRevisions();

    foreach ($revisions as $revision) {
      $diff = $revision->loadActiveDiff();
      if (!$diff) {
        continue;
      }
      $id = $revision->getID();
      $results[] = array(
        'id'          => $id,
        'phid'        => $revision->getPHID(),
        'name'        => $revision->getTitle(),
        'uri'         => PhabricatorEnv::getProductionURI('/D'.$id),
        'dateCreated' => $revision->getDateCreated(),
        'authorPHID'  => $revision->getAuthorPHID(),
        'statusName'  =>
          ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
            $revision->getStatus()),
        'sourcePath'  => $diff->getSourcePath(),
      );
    }

    return $results;
  }

}
