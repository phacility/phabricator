<?php

final class PhabricatorManiphestConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Maniphest');
  }

  public function getDescription() {
    return pht('Configure Maniphest.');
  }

  public function getFontIcon() {
    return 'fa-anchor';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {

    $priority_defaults = array(
      100 => array(
        'name'  => pht('Unbreak Now!'),
        'short' => pht('Unbreak!'),
        'color' => 'pink',
        'keywords' => array('unbreak'),
      ),
      90 => array(
        'name' => pht('Needs Triage'),
        'short' => pht('Triage'),
        'color' => 'violet',
        'keywords' => array('triage'),
      ),
      80 => array(
        'name' => pht('High'),
        'short' => pht('High'),
        'color' => 'red',
        'keywords' => array('high'),
      ),
      50 => array(
        'name' => pht('Normal'),
        'short' => pht('Normal'),
        'color' => 'orange',
        'keywords' => array('normal'),
      ),
      25 => array(
        'name' => pht('Low'),
        'short' => pht('Low'),
        'color' => 'yellow',
        'keywords' => array('low'),
      ),
      0 => array(
        'name' => pht('Wishlist'),
        'short' => pht('Wish'),
        'color' => 'sky',
        'keywords' => array('wish', 'wishlist'),
      ),
    );

    $status_type = 'custom:ManiphestStatusConfigOptionType';
    $status_defaults = array(
      'open' => array(
        'name' => pht('Open'),
        'special' => ManiphestTaskStatus::SPECIAL_DEFAULT,
        'prefixes' => array(
          'open',
          'opens',
          'reopen',
          'reopens',
        ),
      ),
      'resolved' => array(
        'name' => pht('Resolved'),
        'name.full' => pht('Closed, Resolved'),
        'closed' => true,
        'special' => ManiphestTaskStatus::SPECIAL_CLOSED,
        'prefixes' => array(
          'closed',
          'closes',
          'close',
          'fix',
          'fixes',
          'fixed',
          'resolve',
          'resolves',
          'resolved',
        ),
        'suffixes' => array(
          'as resolved',
          'as fixed',
        ),
        'keywords' => array('closed', 'fixed', 'resolved'),
      ),
      'wontfix' => array(
        'name' => pht('Wontfix'),
        'name.full' => pht('Closed, Wontfix'),
        'closed' => true,
        'prefixes' => array(
          'wontfix',
          'wontfixes',
          'wontfixed',
        ),
        'suffixes' => array(
          'as wontfix',
        ),
      ),
      'invalid' => array(
        'name' => pht('Invalid'),
        'name.full' => pht('Closed, Invalid'),
        'closed' => true,
        'prefixes' => array(
          'invalidate',
          'invalidates',
          'invalidated',
        ),
        'suffixes' => array(
          'as invalid',
        ),
      ),
      'duplicate' => array(
        'name' => pht('Duplicate'),
        'name.full' => pht('Closed, Duplicate'),
        'transaction.icon' => 'fa-files-o',
        'special' => ManiphestTaskStatus::SPECIAL_DUPLICATE,
        'closed' => true,
      ),
      'spite' => array(
        'name' => pht('Spite'),
        'name.full' => pht('Closed, Spite'),
        'name.action' => pht('Spited'),
        'transaction.icon' => 'fa-thumbs-o-down',
        'silly' => true,
        'closed' => true,
        'prefixes' => array(
          'spite',
          'spites',
          'spited',
        ),
        'suffixes' => array(
          'out of spite',
          'as spite',
        ),
      ),
    );

    $status_description = $this->deformat(pht(<<<EOTEXT
Allows you to edit, add, or remove the task statuses available in Maniphest,
like "Open", "Resolved" and "Invalid". The configuration should contain a map
of status constants to status specifications (see defaults below for examples).

The constant for each status should be 1-12 characters long and  contain only
lowercase letters and digits. Valid examples are "open", "closed", and
"invalid". Users will not normally see these values.

The keys you can provide in a specification are:

  - `name` //Required string.// Name of the status, like "Invalid".
  - `name.full` //Optional string.// Longer name, like "Closed, Invalid". This
    appears on the task detail view in the header.
  - `name.action` //Optional string.// Action name for email subjects, like
    "Marked Invalid".
  - `closed` //Optional bool.// Statuses are either "open" or "closed".
    Specifying `true` here will mark the status as closed (like "Resolved" or
    "Invalid"). By default, statuses are open.
  - `special` //Optional string.// Mark this status as special. The special
    statuses are:
    - `default` This is the default status for newly created tasks. You must
      designate one status as default, and it must be an open status.
    - `closed` This is the default status for closed tasks (for example, tasks
      closed via the "!close" action in email or via the quick close button in
      Maniphest). You must designate one status as the default closed status,
      and it must be a closed status.
    - `duplicate` This is the status used when tasks are merged into one
      another as duplicates. You must designate one status for duplicates,
      and it must be a closed status.
  - `transaction.icon` //Optional string.// Allows you to choose a different
    icon to use for this status when showing status changes in the transaction
    log. Please see UIExamples, Icons and Images for a list.
  - `transaction.color` //Optional string.// Allows you to choose a different
    color to use for this status when showing status changes in the transaction
    log.
  - `silly` //Optional bool.// Marks this status as silly, and thus wholly
    inappropriate for use by serious businesses.
  - `prefixes` //Optional list<string>.// Allows you to specify a list of
    text prefixes which will trigger a task transition into this status
    when mentioned in a commit message. For example, providing "closes" here
    will allow users to move tasks to this status by writing `Closes T123` in
    commit messages.
  - `suffixes` //Optional list<string>.// Allows you to specify a list of
    text suffixes which will trigger a task transition into this status
    when mentioned in a commit message, after a valid prefix. For example,
    providing "as invalid" here will allow users to move tasks
    to this status by writing `Closes T123 as invalid`, even if another status
    is selected by the "Closes" prefix.
  - `keywords` //Optional list<string>.// Allows you to specify a list
    of keywords which can be used with `!status` commands in email to select
    this status.

Statuses will appear in the UI in the order specified. Note the status marked
`special` as `duplicate` is not settable directly and will not appear in UI
elements, and that any status marked `silly` does not appear if Phabricator
is configured with `phabricator.serious-business` set to true.

Examining the default configuration and examples below will probably be helpful
in understanding these options.

EOTEXT
));

    $status_example = array(
      'open' => array(
        'name' => pht('Open'),
        'special' => 'default',
      ),
      'closed' => array(
        'name' => pht('Closed'),
        'special' => 'closed',
        'closed' => true,
      ),
      'duplicate' => array(
        'name' => pht('Duplicate'),
        'special' => 'duplicate',
        'closed' => true,
      ),
    );

    $json = new PhutilJSON();
    $status_example = $json->encodeFormatted($status_example);

    // This is intentionally blank for now, until we can move more Maniphest
    // logic to custom fields.
    $default_fields = array();

    foreach ($default_fields as $key => $enabled) {
      $default_fields[$key] = array(
        'disabled' => !$enabled,
      );
    }

    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';

    return array(
      $this->newOption('maniphest.custom-field-definitions', 'wild', array())
        ->setSummary(pht('Custom Maniphest fields.'))
        ->setDescription(
          pht(
            'Array of custom fields for Maniphest tasks. For details on '.
            'adding custom fields to Maniphest, see "Configuring Custom '.
            'Fields" in the documentation.'))
        ->addExample(
          '{"mycompany:estimated-hours": {"name": "Estimated Hours", '.
          '"type": "int", "caption": "Estimated number of hours this will '.
          'take."}}',
          pht('Valid Setting')),
      $this->newOption('maniphest.fields', $custom_field_type, $default_fields)
        ->setCustomData(id(new ManiphestTask())->getCustomFieldBaseClass())
        ->setDescription(pht('Select and reorder task fields.')),
      $this->newOption('maniphest.priorities', 'wild', $priority_defaults)
        ->setSummary(pht('Configure Maniphest priority names.'))
        ->setDescription(
          pht(
            'Allows you to edit or override the default priorities available '.
            'in Maniphest, like "High", "Normal" and "Low". The configuration '.
            'should contain a map of priority constants to priority '.
            'specifications (see defaults below for examples).'.
            "\n\n".
            'The keys you can define for a priority are:'.
            "\n\n".
            '  - `name` Name of the priority.'."\n".
            '  - `short` Alternate shorter name, used in UIs where there is '.
            '    not much space available.'."\n".
            '  - `color` A color for this priority, like "red" or "blue".'.
            '  - `keywords` An optional list of keywords which can '.
            '     be used to select this priority when using `!priority` '.
            '     commands in email.'.
            "\n\n".
            'You can choose which priority is the default for newly created '.
            'tasks with `%s`.',
            'maniphest.default-priority')),
      $this->newOption('maniphest.statuses', $status_type, $status_defaults)
        ->setSummary(pht('Configure Maniphest task statuses.'))
        ->setDescription($status_description)
        ->addExample($status_example, pht('Minimal Valid Config')),
      $this->newOption('maniphest.default-priority', 'int', 90)
        ->setSummary(pht('Default task priority for create flows.'))
        ->setDescription(
          pht(
            'Choose a default priority for newly created tasks. You can '.
            'review and adjust available priorities by using the '.
            '%s configuration option. The default value (`90`) '.
            'corresponds to the default "Needs Triage" priority.',
            'maniphest.priorities')),
      $this->newOption(
        'metamta.maniphest.subject-prefix',
        'string',
        '[Maniphest]')
        ->setDescription(pht('Subject prefix for Maniphest mail.')),
      $this->newOption(
        'maniphest.priorities.unbreak-now',
        'int',
        100)
        ->setSummary(pht('Priority used to populate "Unbreak Now" on home.'))
        ->setDescription(
          pht(
            'Temporary setting. If set, this priority is used to populate the '.
            '"Unbreak Now" panel on the home page. You should adjust this if '.
            'you adjust priorities using `%s`.',
            'maniphest.priorities')),
      $this->newOption(
        'maniphest.priorities.needs-triage',
        'int',
        90)
        ->setSummary(pht('Priority used to populate "Needs Triage" on home.'))
        ->setDescription(
          pht(
            'Temporary setting. If set, this priority is used to populate the '.
            '"Needs Triage" panel on the home page. You should adjust this if '.
            'you adjust priorities using `%s`.',
            'maniphest.priorities')),

    );
  }

}
