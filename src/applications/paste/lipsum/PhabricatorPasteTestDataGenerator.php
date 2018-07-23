<?php

final class PhabricatorPasteTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'pastes';

  public function getGeneratorName() {
    return pht('Pastes');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();

    list($name, $language, $content) = $this->newPasteContent();

    $paste = PhabricatorPaste::initializeNewPaste($author);

    $xactions = array();

    $xactions[] = $this->newTransaction(
      PhabricatorPasteTitleTransaction::TRANSACTIONTYPE,
      $name);

    if (strlen($language) > 0) {
        $xactions[] = $this->newTransaction(
            PhabricatorPasteLanguageTransaction::TRANSACTIONTYPE,
            $language);
    }

    $xactions[] = $this->newTransaction(
      PhabricatorPasteContentTransaction::TRANSACTIONTYPE,
      $content);

    $editor = id(new PhabricatorPasteEditor())
      ->setActor($author)
      ->setContentSource($this->getLipsumContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($paste, $xactions);

    return $paste;
  }

  protected function newEmptyTransaction() {
    return new PhabricatorPasteTransaction();
  }

  public function getSupportedLanguages() {
      return array(
          'php' => array(
              'content' => 'PhutilPHPCodeSnippetContextFreeGrammar',
          ),
          'java' => array(
              'content' => 'PhutilJavaCodeSnippetContextFreeGrammar',
          ),
      );
  }

  public function generateContent($spec) {
      $content_generator = idx($spec, 'content');
      if (!$content_generator) {
          $content_generator = 'PhutilLipsumContextFreeGrammar';
      }

      return newv($content_generator, array())
          ->generateSeveral($this->roll(4, 12, 10));
  }

  private function newPasteContent() {
    $languages = $this->getSupportedLanguages();
    $language = array_rand($languages);
    $spec = $languages[$language];

    $title_generator = idx($spec, 'title');
    if (!$title_generator) {
      $title_generator = 'PhabricatorPasteFilenameContextFreeGrammar';
    }

    $title = newv($title_generator, array())
      ->generate();

    $content = $this->generateContent($spec);

    // Usually add the language as a suffix.
    if ($this->roll(1, 20) > 2) {
      $title = $title.'.'.$language;
    }

    switch ($this->roll(1, 20)) {
      case 1:
        // On critical miss, set a different, random language.
        $highlight_as = array_rand($languages);
        break;
      case 18:
      case 19:
      case 20:
        // Sometimes set it to the correct language.
        $highlight_as = $language;
        break;
      default:
        // Usually leave it as autodetect.
        $highlight_as = '';
        break;
    }

    return array($title, $highlight_as, $content);
  }

}
