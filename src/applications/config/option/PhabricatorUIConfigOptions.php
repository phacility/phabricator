<?php

final class PhabricatorUIConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('User Interface');
  }

  public function getDescription() {
    return pht('Configure the Phabricator UI, including colors.');
  }

  public function getFontIcon() {
    return 'fa-magnet';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    $manifest = PHUIIconView::getSheetManifest('main-header');
    $custom_header_example =
      PhabricatorCustomHeaderConfigType::getExampleConfig();
    $experimental_link = 'https://secure.phabricator.com/T4214';
    $options = array();
    foreach (array_keys($manifest) as $sprite_name) {
      $key = substr($sprite_name, strlen('main-header-'));
      $options[$key] = $key;
    }

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

    return array(
      $this->newOption('ui.header-color', 'enum', 'blindigo')
        ->setDescription(
          pht('Sets the color of the main header.'))
        ->setEnumOptions($options),
      $this->newOption('ui.footer-items', 'list<wild>', array())
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
      $this->newOption(
        'ui.custom-header',
        'custom:PhabricatorCustomHeaderConfigType',
        null)
        ->setSummary(
          pht('Customize the Phabricator logo.'))
        ->setDescription(
          pht('You can customize the Phabricator logo by specifying the '.
              'phid for a viewable image you have uploaded to Phabricator '.
              'via the [[ /file/ | Files application]]. This image should '.
              'be:'."\n".
              ' - 192px X 80px; while not enforced, images with these '.
              'dimensions will look best across devices.'."\n".
              ' - have view policy public if [[ '.
              '/config/edit/policy.allow-public | `policy.allow-public`]] '.
              'is true and otherwise view policy user; mismatches in these '.
              'policy settings will result in a broken logo for some users.'.
              "\n\n".
              'You should restart your webserver after updating this value '.
              'to see this change take effect.'.
              "\n\n".
              'As this feature is experimental, please read [[ %s | T4214 ]] '.
              'for up to date information.',
              $experimental_link))
        ->addExample($custom_header_example, pht('Valid Config')),
    );
  }

}
