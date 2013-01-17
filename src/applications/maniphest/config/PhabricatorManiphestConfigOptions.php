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
    return array(
      $this->newOption('maniphest.enabled', 'bool', true)
        ->setDescription(pht("Enable Maniphest")),
      $this->newOption('maniphest.custom-fields', 'wild', array())
        ->setSummary(pht("Custom Maniphest fields."))
        ->setDescription(
          pht(
            "Array of custom fields for Maniphest tasks. For details on ".
            "adding custom fields to Maniphest, see 'Maniphest User Guide: ".
            "Adding Custom Fields'."))
        ->addExample(
          '{"mycompany:estimated-hours": {"label": "Estimated Hours", '.
          '"type": "int", "caption": "Estimated number of hours this will '.
          'take.", "required": false}}',
          pht('Valid Setting')),
      $this->newOption(
        'maniphest.custom-task-extensions-class',
        'class',
        'ManiphestDefaultTaskExtensions')
        ->setBaseClass('ManiphestTaskExtensions')
        ->setSummary(pht("Class which drives custom field construction."))
        ->setDescription(
          pht(
            "Class which drives custom field construction. See 'Maniphest ".
            "User Guide: Adding Custom Fields' in the documentation for more ".
            "information.")),
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
    );
  }

}
