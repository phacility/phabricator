<?php

final class PhrictionHistoryConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.history';
  }

  public function getMethodDescription() {
    return pht('Retrieve history about a Phriction document.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "phriction.content.search" instead.');
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
      'ERR-BAD-DOCUMENT' => pht('No such document exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');
    if ($slug === null || !strlen($slug)) {
      throw new Exception(pht('Field "slug" must be non-empty.'));
    }

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
