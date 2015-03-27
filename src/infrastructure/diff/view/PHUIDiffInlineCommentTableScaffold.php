<?php

/**
 * Wraps an inline comment row scaffold in a table.
 *
 * This scaffold is used to ship inlines over the wire to the client, so they
 * arrive in a form that's easy to mainipulate (a valid table node).
 */
final class PHUIDiffInlineCommentTableScaffold extends AphrontView {

  private $rows = array();

  public function addRowScaffold(PHUIDiffInlineCommentRowScaffold $row) {
    $this->rows[] = $row;
    return $this;
  }

  public function render() {
    return phutil_tag('table', array(), $this->rows);
  }

}
