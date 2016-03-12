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
    $api_type = $item->getItemProperty('api.type');
    switch ($api_type) {
      case 'issue':
        return $this->getGitHubIssueAPIEventDisplayName($item);
      case 'repository':
        return $this->getGitHubRepositoryAPIEventDisplayName($item);
      default:
        return pht('GitHub Event (Unknown API Type "%s")', $api_type);
    }
  }

  private function getGitHubIssueAPIEventDisplayName(NuanceItem $item) {
    $raw = $item->getItemProperty('api.raw', array());

    $action = idxv($raw, array('event'));
    $number = idxv($raw, array('issue', 'number'));

    return pht('GitHub Issue #%d (%s)', $number, $action);
  }

  private function getGitHubRepositoryAPIEventDisplayName(NuanceItem $item) {
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
    $viewer = $this->getViewer();
    $is_dirty = false;

    // TODO: Link up the requestor, etc.

    $source = $item->getSource();
    $token = $source->getSourceProperty('github.token');
    $token = new PhutilOpaqueEnvelope($token);

    $ref = $this->getDoorkeeperRef($item);
    if ($ref) {
      $ref = id(new DoorkeeperImportEngine())
        ->setViewer($viewer)
        ->setRefs(array($ref))
        ->setThrowOnMissingLink(true)
        ->setContextProperty('github.token', $token)
        ->executeOne();

      if ($ref->getSyncFailed()) {
        $xobj = null;
      } else {
        $xobj = $ref->getExternalObject();
      }

      if ($xobj) {
        $item->setItemProperty('doorkeeper.xobj.phid', $xobj->getPHID());
        $is_dirty = true;
      }
    }

    if ($item->getStatus() == NuanceItem::STATUS_IMPORTING) {
      $item->setStatus(NuanceItem::STATUS_ROUTING);
      $is_dirty = true;
    }

    if ($is_dirty) {
      $item->save();
    }
  }

  private function getDoorkeeperRef(NuanceItem $item) {
    $raw = $this->newRawEvent($item);

    $full_repository = $raw->getRepositoryFullName();
    if (!strlen($full_repository)) {
      return null;
    }

    if ($raw->isIssueEvent()) {
      $ref_type = DoorkeeperBridgeGitHubIssue::OBJTYPE_GITHUB_ISSUE;
      $issue_number = $raw->getIssueNumber();
      $full_ref = "{$full_repository}#{$issue_number}";
    } else {
      return null;
    }

    return id(new DoorkeeperObjectRef())
      ->setApplicationType(DoorkeeperBridgeGitHub::APPTYPE_GITHUB)
      ->setApplicationDomain(DoorkeeperBridgeGitHub::APPDOMAIN_GITHUB)
      ->setObjectType($ref_type)
      ->setObjectID($full_ref);
  }

  private function newRawEvent(NuanceItem $item) {
    $type = $item->getItemProperty('api.type');
    $raw = $item->getItemProperty('api.raw', array());

    return NuanceGitHubRawEvent::newEvent($type, $raw);
  }

}
