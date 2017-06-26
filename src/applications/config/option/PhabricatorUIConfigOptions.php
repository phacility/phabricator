<?php

final class PhabricatorUIConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('User Interface');
  }

  public function getDescription() {
    return pht('Configure the Phabricator UI, including colors.');
  }

  public function getIcon() {
    return 'fa-magnet';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    $options = array(
      'blindigo' => pht('Blindigo'),
      'red' => pht('Red'),
      'blue' => pht('Blue'),
      'green' => pht('Green'),
      'indigo' => pht('Indigo'),
      'dark' => pht('Dark'),
    );

    $example = <<<EOJSON
[
  {
    "name" : "Copyright 2199 Examplecorp"
  },
  {
    "name" : "Privacy Policy",
    "href" : "http://www.example.org/privacy/"
  },
  {
    "name" : "Terms and Conditions",
    "href" : "http://www.example.org/terms/"
  }
]
EOJSON;

    $logo_type = 'custom:PhabricatorCustomLogoConfigType';
    $footer_type = 'custom:PhabricatorCustomUIFooterConfigType';

    return array(
      $this->newOption('ui.header-color', 'enum', 'blindigo')
        ->setDescription(
          pht('Sets the default color scheme of Phabricator.'))
        ->setEnumOptions($options),
      $this->newOption('ui.logo', $logo_type, array())
        ->setSummary(
          pht('Customize the logo and wordmark text in the header.'))
        ->setDescription(
          pht(
            "Customize the logo image and text which appears in the main ".
            "site header:\n\n".
            "  - **Logo Image**: Upload a new 80 x 80px image to replace the ".
            "Phabricator logo in the site header.\n\n".
            "  - **Wordmark**: Choose new text to display next to the logo. ".
            "By default, the header displays //Phabricator//.\n\n")),
      $this->newOption('ui.footer-items', $footer_type, array())
        ->setSummary(
          pht(
            'Allows you to add footer links on most pages.'))
        ->setDescription(
          pht(
            "Allows you to add a footer with links in it to most ".
            "pages. You might want to use these links to point at legal ".
            "information or an about page.\n\n".
            "Specify a list of dictionaries. Each dictionary describes ".
            "a footer item. These keys are supported:\n\n".
            "  - `name` The name of the item.\n".
            "  - `href` Optionally, the link target of the item. You can ".
            "    omit this if you just want a piece of text, like a copyright ".
            "    notice."))
        ->addExample($example, pht('Basic Example')),
    );
  }

}
