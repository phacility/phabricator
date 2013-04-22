<?php

final class PhabricatorDiffusionConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Diffusion');
  }

  public function getDescription() {
    return pht('Configure Diffusion repository browsing.');
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.diffusion.subject-prefix',
        'string',
        '[Diffusion]')
        ->setDescription(pht('Subject prefix for Diffusion mail.')),
      $this->newOption(
        'metamta.diffusion.reply-handler-domain',
        'string',
        null)
        ->setDescription(
          pht(
            'See {{metamta.maniphest.reply-handler}}. This does the same '.
            'thing, but affects Diffusion.')),
      $this->newOption(
        'metamta.diffusion.reply-handler',
        'class',
        'PhabricatorAuditReplyHandler')
        ->setBaseClass('PhabricatorMailReplyHandler')
        ->setDescription(pht('Override mail reply handler class.')),
      $this->newOption(
        'metamta.diffusion.attach-patches',
        'bool',
        false)
        ->setBoolOptions(
          array(
            pht("Attach Patches"),
            pht("Do Not Attach Patches"),
          ))
        ->setDescription(pht(
          'Set this to true if you want patches to be attached to commit '.
          'notifications from Diffusion.')),
      $this->newOption('metamta.diffusion.inline-patches', 'int', 0)
        ->setSummary(pht('Include patches in Diffusion mail as body text.'))
        ->setDescription(
          pht(
            'To include patches in Diffusion email bodies, set this to a '.
            'positive integer. Patches will be inlined if they are at most '.
            'that many lines. By default, patches are not inlined.')),
      $this->newOption('metamta.diffusion.byte-limit', 'int', 1024 * 1024)
        ->setDescription(pht('Hard byte limit on including patches in email.')),
      $this->newOption('metamta.diffusion.time-limit', 'int', 60)
        ->setDescription(pht('Hard time limit on generating patches.')),
      $this->newOption(
        'audit.can-author-close-audit',
        'bool',
        false)
        ->setBoolOptions(
          array(
            pht("Enable Closing Audits"),
            pht("Disable Closing Audits"),
          ))
        ->setDescription(pht('Controls whether Author can Close Audits.')),

      $this->newOption('bugtraq.url', 'string', '')
        ->addExample('https://bugs.php.net/%BUGID%', pht('PHP bugs'))
        ->addExample('/%BUGID%', pht('Local Maniphest URL'))
        ->setDescription(pht(
          'URL of external bug tracker used by Diffusion. %s will be '.
            'substituted by the bug ID.',
          '%BUGID%')),
      $this->newOption('bugtraq.logregex', 'list<string>', array())
        ->addExample(array('\B#([1-9]\d*)\b'), pht('Issue #123'))
        ->addExample(array('(?<!#)\b(T[1-9]\d*)\b'), pht('Task T123'))
        ->setDescription(pht(
          'Regular expression to link external bug tracker. See '.
            'http://tortoisesvn.net/docs/release/TortoiseSVN_en/'.
            'tsvn-dug-bugtracker.html for further explanation.')),
    );
  }

}
