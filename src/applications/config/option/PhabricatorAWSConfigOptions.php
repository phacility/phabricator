<?php

final class PhabricatorAWSConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Amazon Web Services');
  }

  public function getDescription() {
    return pht('Configure integration with AWS (EC2, SES, S3, etc).');
  }

  public function getFontIcon() {
    return 'fa-server';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('amazon-ses.access-key', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Access key for Amazon SES.')),
      $this->newOption('amazon-ses.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Secret key for Amazon SES.')),
      $this->newOption('amazon-s3.access-key', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Access key for Amazon S3.')),
      $this->newOption('amazon-s3.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Secret key for Amazon S3.')),
      $this->newOption('amazon-s3.endpoint', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht(
            'Explicit S3 endpoint to use. Leave empty to have Phabricator '.
            'select and endpoint. Normally, you do not need to set this.'))
        ->addExample(null, pht('Use default endpoint'))
        ->addExample('s3.amazon.com', pht('Use specific endpoint')),
      $this->newOption('amazon-ec2.access-key', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Access key for Amazon EC2.')),
      $this->newOption('amazon-ec2.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Secret key for Amazon EC2.')),
    );
  }

}
