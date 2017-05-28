<?php

final class PhabricatorSettingsIssueController
  extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $setup_uri = id(new PhabricatorEmailAddressesSettingsPanel())
      ->setViewer($viewer)
      ->setUser($viewer)
      ->getPanelURI();

    $issues = array();
    if (!$viewer->getIsEmailVerified()) {
      // We could specifically detect that the user has missed email because
      // their address is unverified here and point them at Mail so they can
      // look at messages they missed.

      // We could also detect that an administrator unverified their address
      // and let that come with a message.

      // For now, just make sure the unverified address does not escape notice.
      $issues[] = array(
        'title' => pht('Primary Email Unverified'),
        'summary' => pht(
          'Your primary email address is unverified. You will not be able '.
          'to receive email until you verify it.'),
        'uri' => $setup_uri,
      );
    }

    if ($issues) {
      require_celerity_resource('phabricator-notification-menu-css');

      $items = array();
      foreach ($issues as $issue) {
        $classes = array();
        $classes[] = 'phabricator-notification';
        $classes[] = 'phabricator-notification-unread';

        $uri = $issue['uri'];
        $title = $issue['title'];
        $summary = $issue['summary'];

        $items[] = javelin_tag(
          'div',
          array(
            'class' =>
              'phabricator-notification phabricator-notification-unread',
            'sigil' => 'notification',
            'meta' => array(
              'href' => $uri,
            ),
          ),
          array(
            phutil_tag('strong', array(), pht('%s:', $title)),
            ' ',
            $summary,
          ));
      }

      $content = phutil_tag(
        'div',
        array(
          'class' => 'setup-issue-menu',
        ),
        $items);
    } else {
      $content = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-notification no-notifications',
        ),
        pht('You have no account setup issues.'));
    }

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-notification-header',
      ),
      phutil_tag(
        'a',
        array(
          'href' => $setup_uri,
        ),
        pht('Account Setup Issues')));

    $content = array(
      $header,
      $content,
    );

    $json = array(
      'content' => hsprintf('%s', $content),
      'number'  => count($issues),
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }

}
