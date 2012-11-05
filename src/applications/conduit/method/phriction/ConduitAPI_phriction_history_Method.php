<?php

/**
 * @group conduit
 */
final class ConduitAPI_phriction_history_Method
  extends ConduitAPI_phriction_Method {

  public function getMethodDescription() {
    return "Retrieve history about a Phriction docuemnt.";
  }

  public function defineParamTypes() {
    return array(
      'slug' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty list';
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
