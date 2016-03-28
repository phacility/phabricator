<?php

final class PhrictionCreateConduitAPIMethod extends PhrictionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phriction.create';
  }

  public function getMethodDescription() {
    return pht('Create a Phriction document.');
  }

  protected function defineParamTypes() {
    return array(
      'slug'          => 'required string',
      'title'         => 'required string',
      'content'       => 'required string',
      'description'   => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');
    if (!strlen($slug)) {
      throw new Exception(pht('No such document.'));
    }
    $doc = id(new PhrictionDocumentQuery())
      ->setViewer($request->getUser())
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if ($doc) {
      throw new Exception(pht('Document already exists!'));
    }

    $doc = PhrictionDocument::initializeNewDocument(
      $request->getUser(),
      $slug);

    $xactions = array();
    $xactions[] = id(new PhrictionTransaction())
      ->setTransactionType(PhrictionTransaction::TYPE_TITLE)
      ->setNewValue($request->getValue('title'));
    $xactions[] = id(new PhrictionTransaction())
      ->setTransactionType(PhrictionTransaction::TYPE_CONTENT)
      ->setNewValue($request->getValue('content'));

    $editor = id(new PhrictionTransactionEditor())
      ->setActor($request->getUser())
      ->setContentSource($request->newContentSource())
      ->setContinueOnNoEffect(true)
      ->setDescription($request->getValue('description'));

    try {
      $editor->applyTransactions($doc, $xactions);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      // TODO - some magical hotness via T5873
      throw $ex;
    }

    return $this->buildDocumentInfoDictionary($doc);
  }
}
