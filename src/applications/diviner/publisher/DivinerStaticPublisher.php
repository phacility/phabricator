<?php

final class DivinerStaticPublisher extends DivinerPublisher {

  protected function loadAllPublishedHashes() {
    return array();
  }

  protected function deleteDocumentsByHash(array $hashes) {
    return;
  }

  protected function createDocumentsByHash(array $hashes) {
    foreach ($hashes as $hash) {
      $atom = $this->getAtomFromGraphHash($hash);

      if (!$this->shouldGenerateDocumentForAtom($atom)) {
        continue;
      }

      $content = $this->getRenderer()->renderAtom($atom);
      $this->writeDocument($atom, $content);
    }
  }

  private function writeDocument(DivinerAtom $atom, $content) {
    $root = $this->getConfig('root');
    $path = $root.DIRECTORY_SEPARATOR.$this->getAtomRelativePath($atom);

    if (!Filesystem::pathExists($path)) {
      Filesystem::createDirectory($path, $umask = 0755, $recursive = true);
    }

    Filesystem::writeFile($path.'index.html', $content);
  }

  private function getAtomRelativePath(DivinerAtom $atom) {
    $ref = $atom->getRef();

    $book = $ref->getBook();
    $type = $ref->getType();
    $context = $ref->getContext();
    $name = $ref->getName();

    $path = array(
      'docs',
      $book,
      $type,
    );
    if ($context !== null) {
      $path[] = $context;
    }
    $path[] = $name;

    $index = $this->getAtomSimilarIndex($atom);
    if ($index !== null) {
      $path[] = '@'.$index;
    }

    $path[] = null;

    return implode(DIRECTORY_SEPARATOR, $path);
  }

}
