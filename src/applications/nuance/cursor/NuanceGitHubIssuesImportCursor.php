<?php

final class NuanceGitHubIssuesImportCursor
  extends NuanceGitHubImportCursor {

  const CURSORTYPE = 'github.issues';

  protected function getGitHubAPIEndpointURI($user, $repository) {
    return "/repos/{$user}/{$repository}/issues/events";
  }

  protected function newNuanceItemFromGitHubRecord(array $record) {
    $source = $this->getSource();

    $id = $record['id'];
    $item_key = "github.issueevent.{$id}";

    $container_key = null;

    return NuanceItem::initializeNewItem(NuanceGitHubEventItemType::ITEMTYPE)
      ->setStatus(NuanceItem::STATUS_IMPORTING)
      ->setSourcePHID($source->getPHID())
      ->setItemKey($item_key)
      ->setItemContainerKey($container_key)
      ->setItemProperty('api.type', 'issue')
      ->setItemProperty('api.raw', $record);
  }

}
