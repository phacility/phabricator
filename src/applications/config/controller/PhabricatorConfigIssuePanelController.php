<?php

final class PhabricatorConfigIssuePanelController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $open_items = PhabricatorSetupCheck::getOpenSetupIssueKeys();
    $issues = PhabricatorSetupCheck::runNormalChecks();
    PhabricatorSetupCheck::setOpenSetupIssueKeys(
      PhabricatorSetupCheck::getUnignoredIssueKeys($issues),
      $update_database = true);

    if ($issues) {
      require_celerity_resource('phabricator-notification-menu-css');

      $items = array();
      foreach ($issues as $issue) {
        $classes = array();
        $classes[] = 'phabricator-notification';
        if ($issue->getIsIgnored()) {
          $classes[] = 'phabricator-notification-read';
        } else {
          $classes[] = 'phabricator-notification-unread';
        }
        $uri = '/config/issue/'.$issue->getIssueKey().'/';
        $title = $issue->getName();
        $summary = $issue->getSummary();
        $items[] = javelin_tag(
          'div',
          array(
            'class' => implode(' ', $classes),
            'sigil' => 'notification',
            'meta' => array(
              'href' => $uri,
            ),
          ),
          $title);
      }
      $content = phutil_tag_div('setup-issue-menu', $items);
    } else {
      $content = phutil_tag_div(
        'phabricator-notification no-notifications',
        pht('You have no unresolved setup issues.'));
    }

    $content = hsprintf(
      '<div class="phabricator-notification-header">%s</div>'.
      '%s',
      phutil_tag(
        'a',
        array(
          'href' => '/config/issue/',
        ),
        pht('Unresolved Setup Issues')),
      $content);

    $unresolved_count = count($open_items);

    $json = array(
      'content' => $content,
      'number'  => (int)$unresolved_count,
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }

}
