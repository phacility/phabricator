<?php

final class PhabricatorAWSConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Amazon Web Services');
  }

  public function getDescription() {
    return pht('Configure integration with AWS (EC2, SES, S3, etc).');
  }

  public function getIcon() {
    return 'fa-server';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('amazon-s3.access-key', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Access key for Amazon S3.')),
      $this->newOption('amazon-s3.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Secret key for Amazon S3.')),
      $this->newOption('amazon-s3.region', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht(
            'Amazon S3 region where your S3 bucket is located. When you '.
            'specify a region, you should also specify a corresponding '.
            'endpoint with `amazon-s3.endpoint`. You can find a list of '.
            'available regions and endpoints in the AWS documentation.'))
        ->addExample('us-west-1', pht('USWest Region')),
      $this->newOption('amazon-s3.endpoint', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht(
            'Explicit S3 endpoint to use. This should be the endpoint '.
            'which corresponds to the region you have selected in '.
            '`amazon-s3.region`. This software can not determine the correct '.
            'endpoint automatically because some endpoint locations are '.
            'irregular.'))
        ->addExample(
          's3-us-west-1.amazonaws.com',
          pht('Use specific endpoint')),
      $this->newOption('amazon-ec2.access-key', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Access key for Amazon EC2.')),
      $this->newOption('amazon-ec2.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Secret key for Amazon EC2.')),
    );
  }

}
