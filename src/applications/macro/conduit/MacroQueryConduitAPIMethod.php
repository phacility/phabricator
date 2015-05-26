<?php

final class MacroQueryConduitAPIMethod extends MacroConduitAPIMethod {

  public function getAPIMethodName() {
    return 'macro.query';
  }

  public function getMethodDescription() {
    return pht('Retrieve image macro information.');
  }

  protected function defineParamTypes() {
    return array(
      'authorPHIDs' => 'optional list<phid>',
      'phids'       => 'optional list<phid>',
      'ids'         => 'optional list<id>',
      'names'       => 'optional list<string>',
      'nameLike'    => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorMacroQuery())
      ->setViewer($request->getUser())
      ->needFiles(true);

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
        'dateCreated' => $file->getDateCreated(),
        'filePHID' => $file->getPHID(),
      );
    }

    return $results;
  }

}
