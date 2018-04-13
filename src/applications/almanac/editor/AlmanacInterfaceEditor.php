<?php

final class AlmanacInterfaceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Interface');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this interface.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

}
