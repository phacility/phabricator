<?php

final class PhrictionCreateConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.create';
  }

  public function getMethodDescription() {
    return 'Create a Phriction document.';
  }

  public function defineParamTypes() {
    return array(
      'slug'          => 'required string',
      'title'         => 'required string',
      'content'       => 'required string',
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


    if (!strlen($slug)) {
      throw new Exception(pht('No such document.'));
    }

    $doc = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      #->requireCapabilities(
      #  array(
      #    PhabricatorPolicyCapability::CAN_VIEW,
      #    PhabricatorPolicyCapability::CAN_EDIT,
      #  ))
      ->executeOne();
    if ($doc) {
      throw new Exception(pht('Document already exist!'));
    }

    #$document = new PhrictionDocument();
    #$document->setSlug($slug);
    #$content  = new PhrictionContent();
    #$content->setSlug($slug);
    #$default_title = PhabricatorSlug::getDefaultTitle($slug);
    #$content->setTitle($default_title);
    #$draft = null;
    #$draft_key = null;

    $editor = id(PhrictionDocumentEditor::newForSlug($slug))
      ->setActor($request->getUser())
      ->setTitle($request->getValue('title'))
      ->setContent($request->getValue('content'))
      ->setDescription($request->getvalue('description'))
      ->save();

    return $this->buildDocumentInfoDictionary($editor->getDocument());
  }

}
