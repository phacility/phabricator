<?php

final class PhabricatorManiphestConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Maniphest");
  }

  public function getDescription() {
    return pht("Configure Maniphest.");
  }

  public function getOptions() {

    $priority_defaults = array(
      100 => array(
        'name'  => pht('Unbreak Now!'),
        'short' => pht('Unbreak!'),
        'color' => 'indigo',
      ),
      90 => array(
        'name' => pht('Needs Triage'),
        'short' => pht('Triage'),
        'color' => 'violet',
      ),
      80 => array(
        'name' => pht('High'),
        'short' => pht('High'),
        'color' => 'red',
      ),
      50 => array(
        'name' => pht('Normal'),
        'short' => pht('Normal'),
        'color' => 'orange',
      ),
      25 => array(
        'name' => pht('Low'),
        'short' => pht('Low'),
        'color' => 'yellow',
      ),
      0 => array(
        'name' => pht('Wishlist'),
        'short' => pht('Wish'),
        'color' => 'sky',
      ),
    );

    $status_type = 'custom:ManiphestStatusConfigOptionType';
    $status_defaults = array(
      'open' => array(
        'name' => pht('Open'),
        'special' => ManiphestTaskStatus::SPECIAL_DEFAULT,
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
        'transaction.icon' => 'delete',
        'special' => ManiphestTaskStatus::SPECIAL_DUPLICATE,
        'closed' => true,
      ),
      'spite' => array(
        'name' => pht('Spite'),
        'name.full' => pht('Closed, Spite'),
        'name.action' => pht('Spited'),
        'transaction.icon' => 'dislike',
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
    log.
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

Examining the default configuration and examples below will probably be helpful
in understanding these options.

EOTEXT
));

    $status_example = array(
      'open' => array(
        'name' => 'Open',
        'special' => 'default',
      ),
      'closed' => array(
        'name' => 'Closed',
        'special' => 'closed',
        'closed' => true,
      ),
      'duplicate' => array(
        'name' => 'Duplicate',
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
        ->setSummary(pht("Custom Maniphest fields."))
        ->setDescription(
          pht(
            "Array of custom fields for Maniphest tasks. For details on ".
            "adding custom fields to Maniphest, see 'Maniphest User Guide: ".
            "Adding Custom Fields'."))
        ->addExample(
          '{"mycompany:estimated-hours": {"name": "Estimated Hours", '.
          '"type": "int", "caption": "Estimated number of hours this will '.
          'take."}}',
          pht('Valid Setting')),
      $this->newOption('maniphest.fields', $custom_field_type, $default_fields)
        ->setCustomData(id(new ManiphestTask())->getCustomFieldBaseClass())
        ->setDescription(pht("Select and reorder task fields.")),
      $this->newOption('maniphest.priorities', 'wild', $priority_defaults)
        ->setSummary(pht("Configure Maniphest priority names."))
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
            "\n\n".
            'You can choose which priority is the default for newly created '.
            'tasks with `maniphest.default-priority`.')),
      $this->newOption('maniphest.statuses', $status_type, $status_defaults)
        ->setSummary(pht('Configure Maniphest task statuses.'))
        ->setDescription($status_description)
        ->addExample($status_example, pht('Minimal Valid Config')),
      $this->newOption('maniphest.default-priority', 'int', 90)
        ->setSummary(pht("Default task priority for create flows."))
        ->setDescription(
          pht(
            "What should the default task priority be in create flows? See ".
            "the constants in @{class:ManiphestTaskPriority} for valid ".
            "values. Defaults to 'needs triage'.")),
      $this->newOption(
        'metamta.maniphest.reply-handler-domain',
        'string',
        null)
        ->setSummary(pht('Enable replying to tasks via email.'))
        ->setDescription(
          pht(
            'You can configure a reply handler domain so that email sent from '.
            'Maniphest will have a special "Reply To" address like '.
            '"T123+82+af19f@example.com" that allows recipients to reply by '.
            'email and interact with tasks. For instructions on configurating '.
            'reply handlers, see the article "Configuring Inbound Email" in '.
            'the Phabricator documentation. By default, this is set to `null` '.
            'and Phabricator will use a generic `noreply@` address or the '.
            'address of the acting user instead of a special reply handler '.
            'address (see `metamta.default-address`). If you set a domain '.
            'here, Phabricator will begin generating private reply handler '.
            'addresses. See also `metamta.maniphest.reply-handler` to further '.
            'configure behavior. This key should be set to the domain part '.
            'after the @, like "example.com".')),
      $this->newOption(
        'metamta.maniphest.reply-handler',
        'class',
        'ManiphestReplyHandler')
        ->setBaseClass('PhabricatorMailReplyHandler')
        ->setDescription(pht('Override reply handler class.')),
      $this->newOption(
        'metamta.maniphest.subject-prefix',
        'string',
        '[Maniphest]')
        ->setDescription(pht('Subject prefix for Maniphest mail.')),
      $this->newOption(
        'metamta.maniphest.public-create-email',
        'string',
        null)
        ->setSummary(pht('Allow filing bugs via email.'))
        ->setDescription(
          pht(
            'You can configure an email address like '.
            '"bugs@phabricator.example.com" which will automatically create '.
            'Maniphest tasks when users send email to it. This relies on the '.
            '"From" address to authenticate users, so it is is not completely '.
            'secure. To set this up, enter a complete email address like '.
            '"bugs@phabricator.example.com" and then configure mail to that '.
            'address so it routed to Phabricator (if you\'ve already '.
            'configured reply handlers, you\'re probably already done). See '.
            '"Configuring Inbound Email" in the documentation for more '.
            'information.')),
      $this->newOption(
        'metamta.maniphest.default-public-author',
        'string',
        null)
        ->setSummary(pht('Username anonymous bugs are filed under.'))
        ->setDescription(
          pht(
            'If you enable `metamta.maniphest.public-create-email` and create '.
            'an email address like "bugs@phabricator.example.com", it will '.
            'default to rejecting mail which doesn\'t come from a known user. '.
            'However, you might want to let anyone send email to this '.
            'address; to do so, set a default author here (a Phabricator '.
            'username). A typical use of this might be to create a "System '.
            'Agent" user called "bugs" and use that name here. If you specify '.
            'a valid username, mail will always be accepted and used to '.
            'create a task, even if the sender is not a system user. The '.
            'original email address will be stored in an `From Email` field '.
            'on the task.')),
      $this->newOption(
        'maniphest.priorities.unbreak-now',
        'int',
        100)
        ->setSummary(pht('Priority used to populate "Unbreak Now" on home.'))
        ->setDescription(
          pht(
            'Temporary setting. If set, this priority is used to populate the '.
            '"Unbreak Now" panel on the home page. You should adjust this if '.
            'you adjust priorities using `maniphest.priorities`.')),
      $this->newOption(
        'maniphest.priorities.needs-triage',
        'int',
        90)
        ->setSummary(pht('Priority used to populate "Needs Triage" on home.'))
        ->setDescription(
          pht(
            'Temporary setting. If set, this priority is used to populate the '.
            '"Needs Triage" panel on the home page. You should adjust this if '.
            'you adjust priorities using `maniphest.priorities`.')),

    );
  }

}
