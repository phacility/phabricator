<?php

final class NuanceGitHubRepositoryImportCursor
  extends NuanceGitHubImportCursor {

  const CURSORTYPE = 'github.repository';

  protected function getGitHubAPIEndpointURI($user, $repository) {
    return "/repos/{$user}/{$repository}/events";
  }

  protected function getMaximumPage() {
    return 10;
  }

  protected function getPageSize() {
    return 30;
  }

  protected function newNuanceItemFromGitHubRecord(array $record) {
    $source = $this->getSource();

    $id = $record['id'];
    $item_key = "github.event.{$id}";

    $container_key = null;

    $issue_id = idxv(
      $record,
      array(
        'payload',
        'issue',
        'id',
      ));
    if ($issue_id) {
      $container_key = "github.issue.{$issue_id}";
    }

    return NuanceItem::initializeNewItem()
      ->setStatus(NuanceItem::STATUS_IMPORTING)
      ->setSourcePHID($source->getPHID())
      ->setItemType(NuanceGitHubEventItemType::ITEMTYPE)
      ->setItemKey($item_key)
      ->setItemContainerKey($container_key)
      ->setItemProperty('api.type', 'repository')
      ->setItemProperty('api.raw', $record);
  }

}
