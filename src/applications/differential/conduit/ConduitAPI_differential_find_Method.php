<?php

final class ConduitAPI_differential_find_Method
  extends ConduitAPI_differential_Method {

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
      'open',
      'committable',
      'revision-ids',
      'phids',
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
    $type = $request->getValue('query');
    $guids = $request->getValue('guids');

    $results = array();
    if (!$guids) {
      return $results;
    }

    $query = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser());

    switch ($type) {
      case 'open':
        $query
          ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
          ->withAuthors($guids);
        break;
      case 'committable':
        $query
          ->withStatus(DifferentialRevisionQuery::STATUS_ACCEPTED)
          ->withAuthors($guids);
        break;
      case 'revision-ids':
        $query
          ->withIDs($guids);
        break;
      case 'owned':
        $query->withAuthors($guids);
        break;
      case 'phids':
        $query
          ->withPHIDs($guids);
        break;
    }

    $revisions = $query->execute();

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
