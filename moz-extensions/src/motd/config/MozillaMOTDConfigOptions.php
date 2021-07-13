<?php
/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * This Source Code Form is "Incompatible With Secondary Licenses", as
 * defined by the Mozilla Public License, v. 2.0.
 */

final class MozillaMOTDConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Mozilla MOTD');
  }

  public function getDescription() {
    return pht('Display Message Of The Day.');
  }

  public function getIcon() {
    return 'fa-cog';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'moz-motd.title',
        'string',
        '')
        ->setDescription(pht('Title of the message. Leave empty to disable MOTD.')),
      $this->newOption(
        'moz-motd.message',
        'string',
        '')
        ->setDescription(pht('Body of the message.'))
    );
  }
}
