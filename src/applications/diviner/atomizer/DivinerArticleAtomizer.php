<?php

final class DivinerArticleAtomizer extends DivinerAtomizer {

  public function atomize($file_name, $file_data) {
    $atom = $this->newAtom(DivinerAtom::TYPE_ARTICLE)
      ->setLine(1)
      ->setLength(count(explode("\n", $file_data)))
      ->setLanguage('human');

    $block = "/**\n".str_replace("\n", "\n * ", $file_data)."\n */";
    $atom->setDocblockRaw($block);

    $meta = $atom->getDocblockMeta();
    $title = idx($meta, 'title');
    if (!strlen($title)) {
      $title = 'Untitled Article "'.basename($file_name).'"';
      $atom->addWarning("Article has no @title!");
    }
    $atom->setName($title);

    return array($atom);
  }

}
