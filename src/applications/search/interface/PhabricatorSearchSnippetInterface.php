<?php

/**
 * Objects can implement this interface to provide text snippets in search
 * result views.
 */
interface PhabricatorSearchSnippetInterface {

  public function renderSearchResultSnippet(PhabricatorUser $viewer);

}
