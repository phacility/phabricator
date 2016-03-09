<?php

final class NuanceGitHubEventItemType
  extends NuanceItemType {

  const ITEMTYPE = 'github.event';

  public function getItemTypeDisplayName() {
    return pht('GitHub Event');
  }

  public function getItemTypeDisplayIcon() {
    return 'fa-github';
  }

  public function getItemDisplayName(NuanceItem $item) {
    $raw = $item->getItemProperty('api.raw', array());

    $repo = idxv($raw, array('repo', 'name'), pht('<unknown/unknown>'));

    $type = idx($raw, 'type');
    switch ($type) {
      case 'PushEvent':
        $head = idxv($raw, array('payload', 'head'));
        $head = substr($head, 0, 8);
        $name = pht('Push %s', $head);
        break;
      case 'IssuesEvent':
        $action = idxv($raw, array('payload', 'action'));
        $number = idxv($raw, array('payload', 'issue', 'number'));
        $name = pht('Issue #%d (%s)', $number, $action);
        break;
      case 'IssueCommentEvent':
        $action = idxv($raw, array('payload', 'action'));
        $number = idxv($raw, array('payload', 'issue', 'number'));
        $name = pht('Issue #%d (Comment, %s)', $number, $action);
        break;
      case 'PullRequestEvent':
        $action = idxv($raw, array('payload', 'action'));
        $number = idxv($raw, array('payload', 'pull_request', 'number'));
        $name = pht('Pull Request #%d (%s)', $number, $action);
        break;
      default:
        $name = pht('Unknown Event ("%s")', $type);
        break;
    }

    return pht('GitHub %s %s', $repo, $name);
  }

  public function canUpdateItems() {
    return true;
  }

  protected function updateItemFromSource(NuanceItem $item) {
    // TODO: Link up the requestor, etc.

    if ($item->getStatus() == NuanceItem::STATUS_IMPORTING) {
      $item
        ->setStatus(NuanceItem::STATUS_ROUTING)
        ->save();
    }
  }

}
