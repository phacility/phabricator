<?php

/**
 * @group conduit
 */
final class ConduitAPI_paste_create_Method extends ConduitAPI_paste_Method {

  public function getMethodDescription() {
    return 'Create a new paste.';
  }

  public function defineParamTypes() {
    return array(
      'content'   => 'required string',
      'title'     => 'optional string',
      'language'  => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-NO-PASTE' => 'Paste may not be empty.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $content  = $request->getValue('content');
    $title    = $request->getValue('title');
    $language = $request->getValue('language');

    if (!strlen($content)) {
      throw new ConduitException('ERR-NO-PASTE');
    }

    $title = nonempty($title, 'Masterwork From Distant Lands');
    $language = nonempty($language, '');

    $user = $request->getUser();

    $paste_file = PhabricatorFile::newFromFileData(
      $content,
      array(
        'name'        => $title,
        'mime-type'   => 'text/plain; charset=utf-8',
        'authorPHID'  => $user->getPHID(),
      ));

    $paste = new PhabricatorPaste();
    $paste->setTitle($title);
    $paste->setLanguage($language);
    $paste->setFilePHID($paste_file->getPHID());
    $paste->setAuthorPHID($user->getPHID());
    $paste->setViewPolicy(PhabricatorPolicies::POLICY_USER);
    $paste->save();

    $paste->attachRawContent($content);

    return $this->buildPasteInfoDictionary($paste);
  }

}
