<?php

final class PhabricatorMailSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    $adapter = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');

    switch ($adapter) {
      case 'PhabricatorMailImplementationPHPMailerLiteAdapter':
        if (!Filesystem::pathExists('/usr/bin/sendmail') &&
            !Filesystem::pathExists('/usr/sbin/sendmail')) {
          $message = pht(
            'Mail is configured to send via sendmail, but this system has '.
            'no sendmail binary. Install sendmail or choose a different '.
            'mail adapter.');

          $this->newIssue('config.metamta.mail-adapter')
            ->setShortName(pht('Missing Sendmail'))
            ->setName(pht('No Sendmail Binary Found'))
            ->setMessage($message)
            ->addRelatedPhabricatorConfig('metamta.mail-adapter');
        }
        break;
      case 'PhabricatorMailImplementationAmazonSESAdapter':
        if (PhabricatorEnv::getEnvConfig('metamta.can-send-as-user')) {
          $message = pht(
            'Amazon SES does not support sending email as users. Disable '.
            'send as user, or choose a different mail adapter.');

          $this->newIssue('config.can-send-as-user')
            ->setName(pht("SES Can't Send As User"))
            ->setMessage($message)
            ->addRelatedPhabricatorConfig('metamta.mail-adapter')
            ->addPhabricatorConfig('metamta.can-send-as-user');
        }

        if (!PhabricatorEnv::getEnvConfig('amazon-ses.access-key')) {
          $message = pht(
            'Amazon SES is selected as the mail adapter, but no SES access '.
            'key is configured. Provide an SES access key, or choose a '.
            'different mail adapter.');

          $this->newIssue('config.amazon-ses.access-key')
            ->setName(pht('Amazon SES Access Key Not Set'))
            ->setMessage($message)
            ->addRelatedPhabricatorConfig('metamta.mail-adapter')
            ->addPhabricatorConfig('amazon-ses.access-key');
        }

        if (!PhabricatorEnv::getEnvConfig('amazon-ses.secret-key')) {
          $message = pht(
            'Amazon SES is selected as the mail adapter, but no SES secret '.
            'key is configured. Provide an SES secret key, or choose a '.
            'different mail adapter.');

          $this->newIssue('config.amazon-ses.secret-key')
            ->setName(pht('Amazon SES Secret Key Not Set'))
            ->setMessage($message)
            ->addRelatedPhabricatorConfig('metamta.mail-adapter')
            ->addPhabricatorConfig('amazon-ses.secret-key');
        }

        $address_key = 'metamta.default-address';
        $options = PhabricatorApplicationConfigOptions::loadAllOptions();
        $default = $options[$address_key]->getDefault();
        $value = PhabricatorEnv::getEnvConfig($address_key);
        if ($default === $value) {
          $message = pht(
            'Amazon SES requires verification of the "From" address, but '.
            'you have not configured a "From" address. Configure and verify '.
            'a "From" address, or choose a different mail adapter.');

          $this->newIssue('config.metamta.default-address')
            ->setName(pht('No SES From Address Configured'))
            ->setMessage($message)
            ->addRelatedPhabricatorConfig('metamta.mail-adapter')
            ->addPhabricatorConfig('metamta.default-address');
        }
        break;
    }

  }
}
