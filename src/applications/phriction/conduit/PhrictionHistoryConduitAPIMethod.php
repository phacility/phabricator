<?php

final class PhrictionHistoryConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.history';
  }

  public function getMethodDescription() {
    return pht('Retrieve history about a Phriction document.');
  }

  protected function defineParamTypes() {
    return array(
      'slug' => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-DOCUMENT' => 'No such document exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');
    $doc = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->executeOne();
    if (!$doc) {
      throw new ConduitException('ERR-BAD-DOCUMENT');
    }

    $content = id(new PhrictionContent())->loadAllWhere(
      'documentID = %d ORDER BY version DESC',
      $doc->getID());

    $results = array();
    foreach ($content as $version) {
      $results[] = $this->buildDocumentContentDictionary(
        $doc,
        $version);
    }

    return $results;
  }

}
