<?php

final class PhabricatorPasteTestDataGenerator
  extends PhabricatorTestDataGenerator {

  // Better Support for this in the future
  public $supportedLanguages = array(
    'Java' => 'java',
    'PHP' => 'php',
  );

  public function generate() {
    $author = $this->loadPhabrictorUser();
    $authorphid = $author->getPHID();
    $language = $this->generateLanguage();
    $content = $this->generateContent($language);
    $title = $this->generateTitle($language);
    $paste_file = PhabricatorFile::newFromFileData(
      $content,
      array(
        'name' => $title,
        'mime-type' => 'text/plain; charset=utf-8',
        'authorPHID' => $authorphid,
        ));
    $policy = $this->generatePolicy();
    $filephid = $paste_file->getPHID();
    $parentphid = $this->loadPhabrictorPastePHID();
    $paste = PhabricatorPaste::initializeNewPaste($author)
      ->setParentPHID($parentphid)
      ->setTitle($title)
      ->setLanguage($language)
      ->setViewPolicy($policy)
      ->setEditPolicy($policy)
      ->setFilePHID($filephid)
      ->save();
    return $paste;
  }

  private function loadPhabrictorPastePHID() {
    $random = rand(0, 1);
    if ($random == 1) {
      $paste = id($this->loadOneRandom('PhabricatorPaste'));
      if ($paste) {
        return $paste->getPHID();
      }
    }
    return null;
  }

  public function generateTitle($language = null) {
    $taskgen = new PhutilLipsumContextFreeGrammar();
    // Remove Punctuation
    $title = preg_replace('/[^a-zA-Z 0-9]+/', '', $taskgen->generate());
    // Capitalize First Letters
    $title = ucwords($title);
    // Remove Spaces
    $title = preg_replace('/\s+/', '', $title);
    if ($language == null ||
      !in_array($language, array_keys($this->supportedLanguages))) {
        return $title.'.txt';
    } else {
      return $title.'.'.$this->supportedLanguages[$language];
    }
  }

  public function generateLanguage() {
    $supplemented_lang = $this->supportedLanguages;
    $supplemented_lang['lipsum'] = 'txt';
    return array_rand($supplemented_lang);
  }

  public function generateContent($language = null) {
      if ($language == null ||
        !in_array($language, array_keys($this->supportedLanguages))) {
        return id(new PhutilLipsumContextFreeGrammar())
            ->generateSeveral(rand(30, 40));
      } else {
        $cfg_class = 'Phutil'.$language.'CodeSnippetContextFreeGrammar';
        return newv($cfg_class, array())->generate();
      }
  }

  public function generatePolicy() {
    // Make sure 4/5th of all generated Pastes are viewable to all
    switch (rand(0, 4)) {
      case 0:
        return PhabricatorPolicies::POLICY_PUBLIC;
      case 1:
        return PhabricatorPolicies::POLICY_NOONE;
      default:
        return PhabricatorPolicies::POLICY_USER;
    }
  }
}
