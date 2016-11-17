<?php

final class PhabricatorAuthProvidersGuidanceEngineExtension
  extends PhabricatorGuidanceEngineExtension {

  const GUIDANCEKEY = 'core.auth.providers';

  public function canGenerateGuidance(PhabricatorGuidanceContext $context) {
    return ($context instanceof PhabricatorAuthProvidersGuidanceContext);
  }

  public function generateGuidance(PhabricatorGuidanceContext $context) {
    $domains_key = 'auth.email-domains';
    $domains_link = $this->renderConfigLink($domains_key);
    $domains_value = PhabricatorEnv::getEnvConfig($domains_key);

    $approval_key = 'auth.require-approval';
    $approval_link = $this->renderConfigLink($approval_key);
    $approval_value = PhabricatorEnv::getEnvConfig($approval_key);

    $results = array();

    if ($domains_value) {
      $message = pht(
        'Phabricator is configured with an email domain whitelist (in %s), so '.
        'only users with a verified email address at one of these %s '.
        'allowed domain(s) will be able to register an account: %s',
        $domains_link,
        phutil_count($domains_value),
        phutil_tag('strong', array(), implode(', ', $domains_value)));

      $results[] = $this->newGuidance('core.auth.email-domains.on')
        ->setMessage($message);
    } else {
      $message = pht(
        'Anyone who can browse to this Phabricator install will be able to '.
        'register an account. To add email domain restrictions, configure '.
        '%s.',
        $domains_link);

      $results[] = $this->newGuidance('core.auth.email-domains.off')
        ->setMessage($message);
    }

    if ($approval_value) {
      $message = pht(
        'Administrative approvals are enabled (in %s), so all new users must '.
        'have their accounts approved by an administrator.',
        $approval_link);

      $results[] = $this->newGuidance('core.auth.require-approval.on')
        ->setMessage($message);
    } else {
      $message = pht(
        'Administrative approvals are disabled, so users who register will '.
        'be able to use their accounts immediately. To enable approvals, '.
        'configure %s.',
        $approval_link);

      $results[] = $this->newGuidance('core.auth.require-approval.off')
        ->setMessage($message);
    }

    if (!$domains_value && !$approval_value) {
      $message = pht(
        'You can safely ignore these warnings if the install itself has '.
        'access controls (for example, it is deployed on a VPN) or if all of '.
        'the configured providers have access controls (for example, they are '.
        'all private LDAP or OAuth servers).');

      $results[] = $this->newWarning('core.auth.warning')
        ->setMessage($message);
    }

    return $results;
  }

  private function renderConfigLink($key) {
    return phutil_tag(
      'a',
      array(
        'href' => '/config/edit/'.$key.'/',
        'target' => '_blank',
      ),
      $key);
  }

}
