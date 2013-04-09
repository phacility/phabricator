<?php

/**
 * @group conduit
 */
final class ConduitAPI_macro_query_Method extends ConduitAPI_macro_Method {

  public function getMethodDescription() {
    return "Retrieve image macro information.";
  }

  public function defineParamTypes() {
    return array(
      'authorPHIDs' => 'optional list<phid>',
      'phids'       => 'optional list<phid>',
      'ids'         => 'optional list<id>',
      'names'       => 'optional list<string>',
      'nameLike'    => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new PhabricatorMacroQuery();
    $query->setViewer($request->getUser());

    $author_phids = $request->getValue('authorPHIDs');
    $phids = $request->getValue('phids');
    $ids = $request->getValue('ids');
    $name_like = $request->getValue('nameLike');
    $names = $request->getValue('names');

    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    if ($phids) {
      $query->withPHIDs($phids);
    }

    if ($ids) {
      $query->withIDs($ids);
    }

    if ($name_like) {
      $query->withNameLike($name_like);
    }

    if ($names) {
      $query->withNames($names);
    }

    $macros = $query->execute();

    if (!$macros) {
      return array();
    }

    $results = array();
    foreach ($macros as $macro) {
      $file = $macro->getFile();
      $results[$macro->getName()] = array(
        'uri' => $file->getBestURI(),
        'phid' => $macro->getPHID(),
        'authorPHID' => $file->getAuthorPHID(),
        'dateCreated'   => $file->getDateCreated(),
      );
    }

    return $results;
  }

}
