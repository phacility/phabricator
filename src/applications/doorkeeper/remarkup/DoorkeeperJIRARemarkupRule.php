<?php

final class DoorkeeperJIRARemarkupRule
  extends DoorkeeperRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '@(https?://\S+?)/browse/([A-Z]+-[1-9]\d*)@',
      array($this, 'markupJIRALink'),
      $text);
  }

  public function markupJIRALink($matches) {
    $match_domain = $matches[1];
    $match_issue = $matches[2];

    // TODO: When we support multiple instances, deal with them here.
    $provider = PhabricatorJIRAAuthProvider::getJIRAProvider();
    if (!$provider) {
      return $matches[0];
    }


    $jira_base = $provider->getJIRABaseURI();
    if ($match_domain != rtrim($jira_base, '/')) {
      return $matches[0];
    }

    return $this->addDoorkeeperTag(
      array(
        'href' => $matches[0],
        'tag' => array(
          'ref' => array(
            DoorkeeperBridgeJIRA::APPTYPE_JIRA,
            $provider->getProviderDomain(),
            DoorkeeperBridgeJIRA::OBJTYPE_ISSUE,
            $match_issue,
          ),
        ),
      ));
  }


}
