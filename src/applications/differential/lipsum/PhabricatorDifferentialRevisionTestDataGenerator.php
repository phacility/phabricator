<?php

final class PhabricatorDifferentialRevisionTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function getGeneratorName() {
    return pht('Differential Revisions');
  }

  public function generateObject() {
    $author = $this->loadPhabrictorUser();

    $revision = DifferentialRevision::initializeNewRevision($author);
    $revision->attachReviewerStatus(array());
    $revision->attachActiveDiff(null);

    // This could be a bit richer and more formal than it is.
    $revision->setTitle($this->generateTitle());
    $revision->setSummary($this->generateDescription());
    $revision->setTestPlan($this->generateDescription());

    $diff = $this->generateDiff($author);

    $xactions = array();

    $xactions[] = id(new DifferentialTransaction())
      ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
      ->setNewValue($diff->getPHID());


    id(new DifferentialTransactionEditor())
      ->setActor($author)
      ->setContentSource($this->getLipsumContentSource())
      ->applyTransactions($revision, $xactions);

    return $revision;
  }

  public function getCCPHIDs() {
    $ccs = array();
    for ($i = 0; $i < rand(1, 4);$i++) {
      $ccs[] = $this->loadPhabrictorUserPHID();
    }
    return $ccs;
  }

  public function generateDiff($author) {
    $paste_generator = new PhabricatorPasteTestDataGenerator();
    $languages = $paste_generator->supportedLanguages;
    $lang = array_rand($languages);
    $code = $paste_generator->generateContent($lang);
    $altcode = $paste_generator->generateContent($lang);
    $newcode = $this->randomlyModify($code, $altcode);
    $diff = id(new PhabricatorDifferenceEngine())
      ->generateRawDiffFromFileContent($code, $newcode);
     $call = new ConduitCall(
      'differential.createrawdiff',
      array(
        'diff' => $diff,
      ));
    $call->setUser($author);
    $result = $call->execute();
    $thediff = id(new DifferentialDiff())->load(
      $result['id']);
    $thediff->setDescription($this->generateTitle())->save();
    return $thediff;
  }

  public function generateDescription() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generate(10, 20);
  }

  public function generateTitle() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generate();
  }

  public function randomlyModify($code, $altcode) {
    $codearr = explode("\n", $code);
    $altcodearr = explode("\n", $altcode);
    $no_lines_to_delete = rand(1,
      min(count($codearr) - 2, 5));
    $randomlines = array_rand($codearr,
      count($codearr) - $no_lines_to_delete);
    $newcode = array();
    foreach ($randomlines as $lineno) {
      $newcode[] = $codearr[$lineno];
    }
    $newlines_count = rand(2,
      min(count($codearr) - 2, count($altcodearr) - 2, 5));
    $randomlines_orig = array_rand($codearr, $newlines_count);
    $randomlines_new = array_rand($altcodearr, $newlines_count);
    $newcode2 = array();
    $c = 0;
    for ($i = 0; $i < count($newcode);$i++) {
      $newcode2[] = $newcode[$i];
      if (in_array($i, $randomlines_orig)) {
        $newcode2[] = $altcodearr[$randomlines_new[$c++]];
      }
    }
    return implode($newcode2, "\n");
  }

}
