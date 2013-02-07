<?php

final class PhabricatorDifferentialConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Differential');
  }

  public function getDescription() {
    return pht('Configure Differential code review.');
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'differential.revision-custom-detail-renderer',
        'class',
        null)
        ->setBaseClass('DifferentialRevisionDetailRenderer')
        ->setDescription(pht("Custom revision detail renderer.")),
      $this->newOption(
        'differential.custom-remarkup-rules',
        'list<string>',
        array())
        ->setSummary(pht('Custom remarkup rules.'))
        ->setDescription(
          pht(
            "Array for custom remarkup rules. The array should have a list ".
            "of class names of classes that extend PhutilRemarkupRule")),
      $this->newOption(
        'differential.custom-remarkup-block-rules',
        'list<string>',
        array())
        ->setSummary(pht('Custom remarkup block rules.'))
        ->setDescription(
          pht(
            "Array for custom remarkup block rules. The array should have a ".
            "list of class names of classes that extend ".
            "PhutilRemarkupEngineBlockRule")),
      $this->newOption(
        'differential.whitespace-matters',
        'list<string>',
        array(
          '/\.py$/',
          '/\.l?hs$/',
        ))
        ->setDescription(
          pht(
            "List of file regexps where whitespace is meaningful and should ".
            "not use 'ignore-all' by default")),
      $this->newOption(
        'differential.field-selector',
        'class',
        'DifferentialDefaultFieldSelector')
        ->setBaseClass('DifferentialFieldSelector')
        ->setDescription(pht('Field selector class')),
      $this->newOption('differential.show-host-field', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Show "Host" Fields'),
            pht('Hide "Host" Fields'),
          ))
        ->setSummary(pht('Show or hide the "Host" and "Path" fields.'))
        ->setDescription(
          pht(
            'Differential can show "Host" and "Path" fields on revisions, '.
            'with information about the machine and working directory where '.
            'the change came from. These fields are disabled by default '.
            'because they may occasionally have sensitive information, but '.
            'they can be useful if you work in an environment with shared '.
            'development machines. You can set this option to true to enable '.
            'these fields.')),
      $this->newOption('differential.show-test-plan-field', 'bool', true)
        ->setBoolOptions(
          array(
            pht('Show "Test Plan" Field'),
            pht('Hide "Test Plan" Field'),
          ))
        ->setSummary(pht('Show or hide the "Test Plan" field.'))
        ->setDescription(
          pht(
            'Differential has a required "Test Plan" field by default, which '.
            'requires authors to fill out information about how they verified '.
            'the correctness of their changes when they send code for review. '.
            'If you would prefer not to use this field, you can disable it '.
            'here. You can also make it optional (instead of required) by '.
            'setting {{differential.require-test-plan-field}}.')),
      $this->newOption('differential.require-test-plan-field', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Require 'Test Plan' field"),
            pht("Make 'Test Plan' field optional"),
          ))
        ->setSummary(pht('Require "Test Plan" field?'))
        ->setDescription(
          pht(
            "Differential has a required 'Test Plan' field by default. You ".
            "can make it optional by setting this to false. You can also ".
            "completely remove it above, if you prefer.")),
      $this->newOption('differential.enable-email-accept', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Enable Email "!accept" Action'),
            pht('Disable Email "!accept" Action'),
          ))
        ->setSummary(pht('Enable or disable "!accept" action via email.'))
        ->setDescription(
          pht(
            'If inbound email is configured, users can interact with '.
            'revisions by using "!actions" in email replies (for example, '.
            '"!resign" or "!rethink"). However, by default, users may not '.
            '"!accept" revisions via email: email authentication can be '.
            'configured to be very weak, and email "!accept" is kind of '.
            'sketchy and implies the revision may not actually be receiving '.
            'thorough review. You can enable "!accept" by setting this '.
            'option to true.')),
      $this->newOption('differential.anonymous-access', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Allow guests to view revisions'),
            pht('Require authentication to view revisions'),
          ))
        ->setSummary(pht('Anonymous access to Differential revisions.'))
        ->setDescription(
          pht(
            "If you set this to true, users won't need to login to view ".
            "Differential revisions. Anonymous users will have read-only ".
            "access and won't be able to interact with the revisions.")),
      $this->newOption('differential.generated-paths', 'list<string>', array())
        ->setSummary(pht("File regexps to treat as automatically generated."))
        ->setDescription(
          pht(
            "List of file regexps that should be treated as if they are ".
            "generated by an automatic process, and thus get hidden by ".
            "default in differential."))
        ->addExample('["/config\.h$/", "#/autobuilt/#"]', pht("Valid Setting")),
      $this->newOption('differential.allow-self-accept', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Allow self-accept"),
            pht("Disallow self-accept"),
          ))
        ->setSummary(pht("Allows users to accept their own revisions."))
        ->setDescription(
          pht(
            "If you set this to true, users can accept their own revisions.  ".
            "This action is disabled by default because it's most likely not ".
            "a behavior you want, but it proves useful if you are working ".
            "alone on a project and want to make use of all of ".
            "differential's features.")),
      $this->newOption('differential.always-allow-close', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Allow any user"),
            pht("Restrict to submitter"),
          ))
        ->setSummary(pht("Allows any user to close accepted revisions."))
        ->setDescription(
          pht(
            "If you set this to true, any user can close any revision so ".
            "long as it has been accepted. This can be useful depending on ".
            "your development model. For example, github-style pull requests ".
            "where the reviewer is often the actual committer can benefit ".
            "from turning this option to true. If false, only the submitter ".
            "can close a revision.")),
      $this->newOption('differential.allow-reopen', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable reopen"),
            pht("Disable reopen"),
          ))
        ->setSummary(pht("Allows any user to reopen a closed revision."))
        ->setDescription(
          pht("If you set this to true, any user can reopen a revision so ".
              "long as it has been closed.  This can be useful if a revision ".
              "is accidentally closed or if a developer changes his or her ".
              "mind after closing a revision.  If it is false, reopening ".
              "is not allowed.")),
      $this->newOption('differential.days-fresh', 'int', 1)
        ->setSummary(
          pht(
            "For how many business days should a revision be considered ".
            "'fresh'?"))
        ->setDescription(
          pht(
            "Revisions newer than this number of days are marked as fresh in ".
            "Action Required and Revisions Waiting on You views. Only work ".
            "days (not weekends and holidays) are included. Set to 0 to ".
            "disable this feature.")),
      $this->newOption('differential.days-stale', 'int', 3)
        ->setSummary(
          pht("After this many days, a revision will be considered 'stale'."))
        ->setDescription(
          pht(
            "Similar to `differential.days-fresh` but marks stale revisions. ".
            "If the revision is even older than it is when marked as 'old'.")),
      $this->newOption(
        'metamta.differential.reply-handler-domain',
        'string',
        null)
        ->setDescription(
          pht('Inbound email domain for Differential replies.')),
      $this->newOption(
        'metamta.differential.reply-handler',
        'class',
        'DifferentialReplyHandler')
        ->setBaseClass('PhabricatorMailReplyHandler')
        ->setDescription(pht('Alternate reply handler class.')),
      $this->newOption(
        'metamta.differential.subject-prefix',
        'string',
        '[Differential]')
        ->setDescription(pht('Subject prefix for Differential mail.')),
      $this->newOption(
        'metamta.differential.attach-patches',
        'bool',
        false)
        ->setBoolOptions(
          array(
            pht("Attach Patches"),
            pht("Do Not Attach Patches"),
          ))
        ->setSummary(pht("Attach patches to email, as text attachments."))
        ->setDescription(
          pht(
            "If you set this to true, Phabricator will attach patches to ".
            "Differential mail (as text attachments). This will not work if ".
            "you are using SendGrid as your mail adapter.")),
      $this->newOption(
        'metamta.differential.inline-patches',
        'int',
        0)
        ->setSummary(pht("Inline patches in email, as body text."))
        ->setDescription(
          pht(
            "To include patches inline in email bodies, set this to a ".
            "positive integer. Patches will be inlined if they are at most ".
            "that many lines. For instance, a value of 100 means 'inline ".
            "patches if they are no longer than 100 lines'. By default, ".
            "patches are not inlined.")),
      // TODO: Implement 'enum'? Options are 'unified' or 'git'.
      $this->newOption(
        'metamta.differential.patch-format',
        'string',
        'unified')
        ->setDescription(
          pht("Format for inlined or attached patches: 'git' or 'unified'.")),
      $this->newOption(
        'metamta.differential.unified-comment-context',
        'bool',
        false)
        ->setBoolOptions(
          array(
            pht("Do not show context"),
            pht("Show context"),
          ))
        ->setSummary(pht("Show diff context around inline comments in email."))
        ->setDescription(
          pht(
            "Normally, inline comments in emails are shown with a file and ".
            "line but without any diff context. Enabling this option adds ".
            "diff context.")),
    );
  }

}
