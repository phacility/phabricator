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

    // TODO: This should be utf8-aware, but we don't currently have a plain-text
    // utf8 hard-wrap function. See T2554.
    $old = wordwrap($old, 80);
    $new = wordwrap($new, 80);

    $engine = new PhabricatorDifferenceEngine();
    $changeset = $engine->generateChangesetFromFileContent($old, $new);

    $whitespace_mode = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;

    $parser = new DifferentialChangesetParser();
    $parser->setChangeset($changeset);
    $parser->setMarkupEngine(new PhabricatorMarkupEngine());
    $parser->setWhitespaceMode($whitespace_mode);

    return $parser->render(0, PHP_INT_MAX, array());
  }

}

