<?php

final class PasteCreateConduitAPIMethod extends PasteConduitAPIMethod {

  public function getAPIMethodName() {
    return 'paste.create';
  }

  public function getMethodDescription() {
    return pht('Create a new paste.');
  }

  protected function defineParamTypes() {
    return array(
      'content'   => 'required string',
      'title'     => 'optional string',
      'language'  => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-NO-PASTE' => pht('Paste may not be empty.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $content  = $request->getValue('content');
    $title    = $request->getValue('title');
    $language = $request->getValue('language');

    if (!strlen($content)) {
      throw new ConduitException('ERR-NO-PASTE');
    }

    $title = nonempty($title, pht('Masterwork From Distant Lands'));
    $language = nonempty($language, '');

    $viewer = $request->getUser();

    $paste = PhabricatorPaste::initializeNewPaste($viewer);

    $file = PhabricatorPasteEditor::initializeFileForPaste(
      $viewer,
      $title,
      $content);

    $xactions = array();

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_CONTENT)
      ->setNewValue($file->getPHID());

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_TITLE)
      ->setNewValue($title);

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_LANGUAGE)
      ->setNewValue($language);

    $editor = id(new PhabricatorPasteEditor())
      ->setActor($viewer)
      ->setContentSourceFromConduitRequest($request);

    $xactions = $editor->applyTransactions($paste, $xactions);

    $paste->attachRawContent($content);
    return $this->buildPasteInfoDictionary($paste);
  }

}
