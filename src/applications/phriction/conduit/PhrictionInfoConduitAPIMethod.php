<?php

final class PhrictionInfoConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.info';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about a Phriction document.');
  }

  public function defineParamTypes() {
    return array(
      'slug' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-DOCUMENT' => 'No such document exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->needContent(true)
      ->executeOne();
    if (!$document) {
      throw new ConduitException('ERR-BAD-DOCUMENT');
    }

    return $this->buildDocumentInfoDictionary(
      $document,
      $document->getContent());
  }

}
