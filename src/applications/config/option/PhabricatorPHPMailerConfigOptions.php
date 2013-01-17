<?php

final class PhabricatorPHPMailerConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("PHPMailer");
  }

  public function getDescription() {
    return pht("Configure PHPMailer.");
  }

  public function getOptions() {
    return array(
      $this->newOption('phpmailer.mailer', 'string', 'smtp')
        ->setSummary(pht("Configure mailer used by PHPMailer."))
        ->setDescription(
          pht(
            "If you're using PHPMailer to send email, provide the mailer and ".
            "options here. PHPMailer is much more enormous than ".
            "PHPMailerLite, and provides more mailers and greater enormity. ".
            "You need it when you want to use SMTP instead of sendmail as the ".
            "mailer.")),
      $this->newOption('phpmailer.smtp-host', 'string', null)
        ->setDescription(pht('Host for SMTP.')),
      $this->newOption('phpmailer.smtp-port', 'int', 25)
        ->setDescription(pht('Port for SMTP.')),
      // TODO: Implement "enum"? Valid values are empty, 'tls', or 'ssl'.
      $this->newOption('phpmailer.smtp-protocol', 'string', null)
        ->setSummary(pht('Configure TLS or SSL for SMTP.'))
        ->setDescription(
          pht(
            "Using PHPMailer with SMTP, you can set this to one of 'tls' or ".
            "'ssl' to use TLS or SSL, respectively. Leave it blank for ".
            "vanilla SMTP. If you're sending via Gmail, set it to 'ssl'.")),
      $this->newOption('phpmailer.smtp-user', 'string', null)
        ->setDescription(pht('Username for SMTP.')),
      $this->newOption('phpmailer.smtp-password', 'string', null)
        ->setMasked(true)
        ->setDescription(pht('Password for SMTP.')),
    );
  }

}
