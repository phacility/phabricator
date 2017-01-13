<?php

final class PasteCreateConduitAPIMethod extends PasteConduitAPIMethod {

  public function getAPIMethodName() {
    return 'paste.create';
  }

  public function getMethodDescription() {
    return pht('Create a new paste.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "paste.edit" instead.');
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

    $xactions = array();

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteContentTransaction::TRANSACTIONTYPE)
      ->setNewValue($content);

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTitleTransaction::TRANSACTIONTYPE)
      ->setNewValue($title);

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteLanguageTransaction::TRANSACTIONTYPE)
      ->setNewValue($language);

    $editor = id(new PhabricatorPasteEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContentSource($request->newContentSource());

    $xactions = $editor->applyTransactions($paste, $xactions);

    $paste->attachRawContent($content);
    return $this->buildPasteInfoDictionary($paste);
  }

}
