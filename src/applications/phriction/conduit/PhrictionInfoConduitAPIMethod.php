<?php

final class PhrictionInfoConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.info';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about a Phriction document.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "phriction.document.search" instead.');
  }

  protected function defineParamTypes() {
    return array(
      'slug' => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
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

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->needContent(true)
      ->executeOne();
    if (!$document) {
      throw new ConduitException('ERR-BAD-DOCUMENT');
    }

    return $this->buildDocumentInfoDictionary($document);
  }

}
