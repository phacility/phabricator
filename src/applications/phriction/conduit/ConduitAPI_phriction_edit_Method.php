<?php

final class ConduitAPI_phriction_edit_Method
  extends ConduitAPI_phriction_Method {

  public function getMethodDescription() {
    return "Update a Phriction document.";
  }

  public function defineParamTypes() {
    return array(
      'slug'          => 'required string',
      'title'         => 'optional string',
      'content'       => 'optional string',
      'description'   => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');

    $doc = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$doc) {
      throw new Exception(pht('No such document.'));
    }

    $editor = id(PhrictionDocumentEditor::newForSlug($slug))
      ->setActor($request->getUser())
      ->setTitle($request->getValue('title'))
      ->setContent($request->getValue('content'))
      ->setDescription($request->getvalue('description'))
      ->save();

    return $this->buildDocumentInfoDictionary($editor->getDocument());
  }

}
