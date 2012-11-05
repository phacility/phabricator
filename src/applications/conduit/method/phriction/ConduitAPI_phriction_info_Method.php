<?php

/**
 * @group conduit
 */
final class ConduitAPI_phriction_info_Method
  extends ConduitAPI_phriction_Method {

  public function getMethodDescription() {
    return "Retrieve information about a Phriction document.";
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

    $doc = id(new PhrictionDocument())->loadOneWhere(
      'slug = %s',
      PhabricatorSlug::normalize($slug));

    if (!$doc) {
      throw new ConduitException('ERR-BAD-DOCUMENT');
    }

    $content = id(new PhrictionContent())->load($doc->getContentID());
    $doc->attachContent($content);

    return $this->buildDocumentInfoDictionary($doc);
  }

}
