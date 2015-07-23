<?php

final class PhabricatorApplicationTransactionTextDiffDetailView
  extends AphrontView {

  private $oldText;
  private $newText;

  public function setNewText($new_text) {
    $this->newText = $new_text;
    return $this;
  }

  public function setOldText($old_text) {
    $this->oldText = $old_text;
    return $this;
  }

  public function render() {
    $old = $this->oldText;
    $new = $this->newText;

    // TODO: On mobile, or perhaps by default, we should switch to 1-up once
    // that is built.

    if (strlen($old)) {
      $old = phutil_utf8_hard_wrap($old, 80);
      $old = implode("\n", $old)."\n";
    }

    if (strlen($new)) {
      $new = phutil_utf8_hard_wrap($new, 80);
      $new = implode("\n", $new)."\n";
    }

    try {
      $engine = new PhabricatorDifferenceEngine();
      $changeset = $engine->generateChangesetFromFileContent($old, $new);

      $whitespace_mode = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;

      $markup_engine = new PhabricatorMarkupEngine();
      $markup_engine->setViewer($this->getUser());

      $parser = new DifferentialChangesetParser();
      $parser->setUser($this->getUser());
      $parser->setChangeset($changeset);
      $parser->setMarkupEngine($markup_engine);
      $parser->setWhitespaceMode($whitespace_mode);

      return $parser->render(0, PHP_INT_MAX, array());
    } catch (Exception $ex) {
      return $ex->getMessage();
    }
  }

}
