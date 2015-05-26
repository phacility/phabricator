<?php

final class DivinerArticleAtomizer extends DivinerAtomizer {

  protected function executeAtomize($file_name, $file_data) {
    $atom = $this->newAtom(DivinerAtom::TYPE_ARTICLE)
      ->setLine(1)
      ->setLength(count(explode("\n", $file_data)))
      ->setLanguage('human');

    $block = "/**\n".str_replace("\n", "\n * ", $file_data)."\n */";
    $atom->setDocblockRaw($block);

    $meta = $atom->getDocblockMeta();

    $title = idx($meta, 'title');
    if (!strlen($title)) {
      $title = pht('Untitled Article "%s"', basename($file_name));
      $atom->addWarning(pht('Article has no %s!', '@title'));
      $atom->setDocblockMetaValue('title', $title);
    }

    // If the article has no @name, use the filename after stripping any
    // extension.
    $name = idx($meta, 'name');
    if (!$name) {
      $name = basename($file_name);
      $name = preg_replace('/\\.[^.]+$/', '', $name);
    }
    $atom->setName($name);

    return array($atom);
  }

}
