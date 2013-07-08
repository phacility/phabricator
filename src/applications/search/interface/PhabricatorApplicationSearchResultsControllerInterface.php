<?php

interface PhabricatorApplicationSearchResultsControllerInterface {

  public function renderResultsList(
    array $items,
    PhabricatorSavedQuery $query);

}
