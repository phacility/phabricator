<?php

final class DivinerFileAtomizer extends DivinerAtomizer {

  protected function executeAtomize($file_name, $file_data) {
    $atom = $this->newAtom(DivinerAtom::TYPE_FILE)
      ->setName($file_name)
      ->setFile($file_name)
      ->setContentRaw($file_data);

    return array($atom);
  }

}
