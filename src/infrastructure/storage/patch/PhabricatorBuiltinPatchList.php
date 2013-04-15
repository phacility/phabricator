<?php

final class PhabricatorBuiltinPatchList extends PhabricatorSQLPatchList {

  public function getNamespace() {
    return 'phabricator';
  }

  private function getPatchPath($file) {
    $root = dirname(phutil_get_library_root('phabricator'));
    $path = $root.'/resources/sql/patches/'.$file;

    // Make sure it exists.
    Filesystem::readFile($path);

    return $path;
  }

  public function getPatches() {
    return array(
      'db.audit' => array(
        'type'  => 'db',
        'name'  => 'audit',
        'after' => array( /* First Patch */ ),
      ),
      'db.calendar' => array(
        'type'  => 'db',
        'name'  => 'calendar',
      ),
      'db.chatlog' => array(
        'type'  => 'db',
        'name'  => 'chatlog',
      ),
      'db.conduit' => array(
        'type'  => 'db',
        'name'  => 'conduit',
      ),
      'db.countdown' => array(
        'type'  => 'db',
        'name'  => 'countdown',
      ),
      'db.daemon' => array(
        'type'  => 'db',
        'name'  => 'daemon',
      ),
      'db.differential' => array(
        'type'  => 'db',
        'name'  => 'differential',
      ),
      'db.draft' => array(
        'type'  => 'db',
        'name'  => 'draft',
      ),
      'db.drydock' => array(
        'type'  => 'db',
        'name'  => 'drydock',
      ),
      'db.feed' => array(
        'type'  => 'db',
        'name'  => 'feed',
      ),
      'db.file' => array(
        'type'  => 'db',
        'name'  => 'file',
      ),
      'db.flag' => array(
        'type'  => 'db',
        'name'  => 'flag',
      ),
      'db.harbormaster' => array(
        'type'  => 'db',
        'name'  => 'harbormaster',
      ),
      'db.herald' => array(
        'type'  => 'db',
        'name'  => 'herald',
      ),
      'db.maniphest' => array(
        'type'  => 'db',
        'name'  => 'maniphest',
      ),
      'db.meta_data' => array(
        'type'  => 'db',
        'name'  => 'meta_data',
      ),
      'db.metamta' => array(
        'type'  => 'db',
        'name'  => 'metamta',
      ),
      'db.oauth_server' => array(
        'type'  => 'db',
        'name'  => 'oauth_server',
      ),
      'db.owners' => array(
        'type'  => 'db',
        'name'  => 'owners',
      ),
      'db.pastebin' => array(
        'type'  => 'db',
        'name'  => 'pastebin',
      ),
      'db.phame' => array(
        'type'  => 'db',
        'name'  => 'phame',
      ),
      'db.phriction' => array(
        'type'  => 'db',
        'name'  => 'phriction',
      ),
      'db.project' => array(
        'type'  => 'db',
        'name'  => 'project',
      ),
      'db.repository' => array(
        'type'  => 'db',
        'name'  => 'repository',
      ),
      'db.search' => array(
        'type'  => 'db',
        'name'  => 'search',
      ),
      'db.slowvote' => array(
        'type'  => 'db',
        'name'  => 'slowvote',
      ),
      'db.timeline' => array(
        'type'  => 'db',
        'name'  => 'timeline',
      ),
      'db.user' => array(
        'type'  => 'db',
        'name'  => 'user',
      ),
      'db.worker' => array(
        'type'  => 'db',
        'name'  => 'worker',
      ),
      'db.xhpastview' => array(
        'type'  => 'db',
        'name'  => 'xhpastview',
      ),
      'db.cache' => array(
        'type'  => 'db',
        'name'  => 'cache',
      ),
      'db.fact' => array(
        'type'  => 'db',
        'name'  => 'fact',
      ),
      'db.ponder' => array(
        'type'    => 'db',
        'name'    => 'ponder',
      ),
      'db.xhprof' => array(
        'type'    => 'db',
        'name'    => 'xhprof',
      ),
      'db.pholio' => array(
        'type'    => 'db',
        'name'    => 'pholio',
      ),
      'db.conpherence' => array(
        'type'    => 'db',
        'name'    => 'conpherence',
      ),
      'db.config' => array(
        'type'    => 'db',
        'name'    => 'config',
      ),
      'db.token' => array(
        'type'    => 'db',
        'name'    => 'token',
      ),
      'db.releeph' => array(
        'type'    => 'db',
        'name'    => 'releeph',
      ),
      'db.phlux' => array(
        'type' => 'db',
        'name' => 'phlux',
      ),
      'db.phortune' => array(
        'type' => 'db',
        'name' => 'phortune',
      ),
      'db.phrequent' => array(
        'type' => 'db',
        'name' => 'phrequent',
      ),
      '0000.legacy.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('0000.legacy.sql'),
        'legacy'  => 0,
      ),
      '000.project.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('000.project.sql'),
        'legacy'  => 0,
      ),
      '001.maniphest_projects.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('001.maniphest_projects.sql'),
        'legacy'  => 1,
      ),
      '002.oauth.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('002.oauth.sql'),
        'legacy'  => 2,
      ),
      '003.more_oauth.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('003.more_oauth.sql'),
        'legacy'  => 3,
      ),
      '004.daemonrepos.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('004.daemonrepos.sql'),
        'legacy'  => 4,
      ),
      '005.workers.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('005.workers.sql'),
        'legacy'  => 5,
      ),
      '006.repository.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('006.repository.sql'),
        'legacy'  => 6,
      ),
      '007.daemonlog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('007.daemonlog.sql'),
        'legacy'  => 7,
      ),
      '008.repoopt.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('008.repoopt.sql'),
        'legacy'  => 8,
      ),
      '009.repo_summary.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('009.repo_summary.sql'),
        'legacy'  => 9,
      ),
      '010.herald.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('010.herald.sql'),
        'legacy'  => 10,
      ),
      '011.badcommit.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('011.badcommit.sql'),
        'legacy'  => 11,
      ),
      '012.dropphidtype.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('012.dropphidtype.sql'),
        'legacy'  => 12,
      ),
      '013.commitdetail.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('013.commitdetail.sql'),
        'legacy'  => 13,
      ),
      '014.shortcuts.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('014.shortcuts.sql'),
        'legacy'  => 14,
      ),
      '015.preferences.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('015.preferences.sql'),
        'legacy'  => 15,
      ),
      '016.userrealnameindex.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('016.userrealnameindex.sql'),
        'legacy'  => 16,
      ),
      '017.sessionkeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('017.sessionkeys.sql'),
        'legacy'  => 17,
      ),
      '018.owners.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('018.owners.sql'),
        'legacy'  => 18,
      ),
      '019.arcprojects.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('019.arcprojects.sql'),
        'legacy'  => 19,
      ),
      '020.pathcapital.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('020.pathcapital.sql'),
        'legacy'  => 20,
      ),
      '021.xhpastview.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('021.xhpastview.sql'),
        'legacy'  => 21,
      ),
      '022.differentialcommit.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('022.differentialcommit.sql'),
        'legacy'  => 22,
      ),
      '023.dxkeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('023.dxkeys.sql'),
        'legacy'  => 23,
      ),
      '024.mlistkeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('024.mlistkeys.sql'),
        'legacy'  => 24,
      ),
      '025.commentopt.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('025.commentopt.sql'),
        'legacy'  => 25,
      ),
      '026.diffpropkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('026.diffpropkey.sql'),
        'legacy'  => 26,
      ),
      '027.metamtakeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('027.metamtakeys.sql'),
        'legacy'  => 27,
      ),
      '028.systemagent.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('028.systemagent.sql'),
        'legacy'  => 28,
      ),
      '029.cursors.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('029.cursors.sql'),
        'legacy'  => 29,
      ),
      '030.imagemacro.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('030.imagemacro.sql'),
        'legacy'  => 30,
      ),
      '031.workerrace.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('031.workerrace.sql'),
        'legacy'  => 31,
      ),
      '032.viewtime.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('032.viewtime.sql'),
        'legacy'  => 32,
      ),
      '033.privtest.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('033.privtest.sql'),
        'legacy'  => 33,
      ),
      '034.savedheader.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('034.savedheader.sql'),
        'legacy'  => 34,
      ),
      '035.proxyimage.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('035.proxyimage.sql'),
        'legacy'  => 35,
      ),
      '036.mailkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('036.mailkey.sql'),
        'legacy'  => 36,
      ),
      '037.setuptest.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('037.setuptest.sql'),
        'legacy'  => 37,
      ),
      '038.admin.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('038.admin.sql'),
        'legacy'  => 38,
      ),
      '039.userlog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('039.userlog.sql'),
        'legacy'  => 39,
      ),
      '040.transform.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('040.transform.sql'),
        'legacy'  => 40,
      ),
      '041.heraldrepetition.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('041.heraldrepetition.sql'),
        'legacy'  => 41,
      ),
      '042.commentmetadata.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('042.commentmetadata.sql'),
        'legacy'  => 42,
      ),
      '043.pastebin.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('043.pastebin.sql'),
        'legacy'  => 43,
      ),
      '044.countdown.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('044.countdown.sql'),
        'legacy'  => 44,
      ),
      '045.timezone.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('045.timezone.sql'),
        'legacy'  => 45,
      ),
      '046.conduittoken.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('046.conduittoken.sql'),
        'legacy'  => 46,
      ),
      '047.projectstatus.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('047.projectstatus.sql'),
        'legacy'  => 47,
      ),
      '048.relationshipkeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('048.relationshipkeys.sql'),
        'legacy'  => 48,
      ),
      '049.projectowner.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('049.projectowner.sql'),
        'legacy'  => 49,
      ),
      '050.taskdenormal.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('050.taskdenormal.sql'),
        'legacy'  => 50,
      ),
      '051.projectfilter.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('051.projectfilter.sql'),
        'legacy'  => 51,
      ),
      '052.pastelanguage.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('052.pastelanguage.sql'),
        'legacy'  => 52,
      ),
      '053.feed.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('053.feed.sql'),
        'legacy'  => 53,
      ),
      '054.subscribers.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('054.subscribers.sql'),
        'legacy'  => 54,
      ),
      '055.add_author_to_files.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('055.add_author_to_files.sql'),
        'legacy'  => 55,
      ),
      '056.slowvote.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('056.slowvote.sql'),
        'legacy'  => 56,
      ),
      '057.parsecache.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('057.parsecache.sql'),
        'legacy'  => 57,
      ),
      '058.missingkeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('058.missingkeys.sql'),
        'legacy'  => 58,
      ),
      '059.engines.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('059.engines.php'),
        'legacy'  => 59,
      ),
      '060.phriction.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('060.phriction.sql'),
        'legacy'  => 60,
      ),
      '061.phrictioncontent.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('061.phrictioncontent.sql'),
        'legacy'  => 61,
      ),
      '062.phrictionmenu.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('062.phrictionmenu.sql'),
        'legacy'  => 62,
      ),
      '063.pasteforks.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('063.pasteforks.sql'),
        'legacy'  => 63,
      ),
      '064.subprojects.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('064.subprojects.sql'),
        'legacy'  => 64,
      ),
      '065.sshkeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('065.sshkeys.sql'),
        'legacy'  => 65,
      ),
      '066.phrictioncontent.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('066.phrictioncontent.sql'),
        'legacy'  => 66,
      ),
      '067.preferences.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('067.preferences.sql'),
        'legacy'  => 67,
      ),
      '068.maniphestauxiliarystorage.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('068.maniphestauxiliarystorage.sql'),
        'legacy'  => 68,
      ),
      '069.heraldxscript.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('069.heraldxscript.sql'),
        'legacy'  => 69,
      ),
      '070.differentialaux.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('070.differentialaux.sql'),
        'legacy'  => 70,
      ),
      '071.contentsource.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('071.contentsource.sql'),
        'legacy'  => 71,
      ),
      '072.blamerevert.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('072.blamerevert.sql'),
        'legacy'  => 72,
      ),
      '073.reposymbols.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('073.reposymbols.sql'),
        'legacy'  => 73,
      ),
      '074.affectedpath.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('074.affectedpath.sql'),
        'legacy'  => 74,
      ),
      '075.revisionhash.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('075.revisionhash.sql'),
        'legacy'  => 75,
      ),
      '076.indexedlanguages.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('076.indexedlanguages.sql'),
        'legacy'  => 76,
      ),
      '077.originalemail.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('077.originalemail.sql'),
        'legacy'  => 77,
      ),
      '078.nametoken.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('078.nametoken.sql'),
        'legacy'  => 78,
      ),
      '079.nametokenindex.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('079.nametokenindex.php'),
        'legacy'  => 79,
      ),
      '080.filekeys.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('080.filekeys.sql'),
        'legacy'  => 80,
      ),
      '081.filekeys.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('081.filekeys.php'),
        'legacy'  => 81,
      ),
      '082.xactionkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('082.xactionkey.sql'),
        'legacy'  => 82,
      ),
      '083.dxviewtime.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('083.dxviewtime.sql'),
        'legacy'  => 83,
      ),
      '084.pasteauthorkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('084.pasteauthorkey.sql'),
        'legacy'  => 84,
      ),
      '085.packagecommitrelationship.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('085.packagecommitrelationship.sql'),
        'legacy'  => 85,
      ),
      '086.formeraffil.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('086.formeraffil.sql'),
        'legacy'  => 86,
      ),
      '087.phrictiondelete.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('087.phrictiondelete.sql'),
        'legacy'  => 87,
      ),
      '088.audit.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('088.audit.sql'),
        'legacy'  => 88,
      ),
      '089.projectwiki.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('089.projectwiki.sql'),
        'legacy'  => 89,
      ),
      '090.forceuniqueprojectnames.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('090.forceuniqueprojectnames.php'),
        'legacy'  => 90,
      ),
      '091.uniqueslugkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('091.uniqueslugkey.sql'),
        'legacy'  => 91,
      ),
      '092.dropgithubnotification.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('092.dropgithubnotification.sql'),
        'legacy'  => 92,
      ),
      '093.gitremotes.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('093.gitremotes.php'),
        'legacy'  => 93,
      ),
      '094.phrictioncolumn.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('094.phrictioncolumn.sql'),
        'legacy'  => 94,
      ),
      '095.directory.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('095.directory.sql'),
        'legacy'  => 95,
      ),
      '096.filename.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('096.filename.sql'),
        'legacy'  => 96,
      ),
      '097.heraldruletypes.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('097.heraldruletypes.sql'),
        'legacy'  => 97,
      ),
      '098.heraldruletypemigration.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('098.heraldruletypemigration.php'),
        'legacy'  => 98,
      ),
      '099.drydock.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('099.drydock.sql'),
        'legacy'  => 99,
      ),
      '100.projectxaction.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('100.projectxaction.sql'),
        'legacy'  => 100,
      ),
      '101.heraldruleapplied.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('101.heraldruleapplied.sql'),
        'legacy'  => 101,
      ),
      '102.heraldcleanup.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('102.heraldcleanup.php'),
        'legacy'  => 102,
      ),
      '103.heraldedithistory.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('103.heraldedithistory.sql'),
        'legacy'  => 103,
      ),
      '104.searchkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('104.searchkey.sql'),
        'legacy'  => 104,
      ),
      '105.mimetype.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('105.mimetype.sql'),
        'legacy'  => 105,
      ),
      '106.chatlog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('106.chatlog.sql'),
        'legacy'  => 106,
      ),
      '107.oauthserver.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('107.oauthserver.sql'),
        'legacy'  => 107,
      ),
      '108.oauthscope.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('108.oauthscope.sql'),
        'legacy'  => 108,
      ),
      '109.oauthclientphidkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('109.oauthclientphidkey.sql'),
        'legacy'  => 109,
      ),
      '110.commitaudit.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('110.commitaudit.sql'),
        'legacy'  => 110,
      ),
      '111.commitauditmigration.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('111.commitauditmigration.php'),
        'legacy'  => 111,
      ),
      '112.oauthaccesscoderedirecturi.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('112.oauthaccesscoderedirecturi.sql'),
        'legacy'  => 112,
      ),
      '113.lastreviewer.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('113.lastreviewer.sql'),
        'legacy'  => 113,
      ),
      '114.auditrequest.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('114.auditrequest.sql'),
        'legacy'  => 114,
      ),
      '115.prepareutf8.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('115.prepareutf8.sql'),
        'legacy'  => 115,
      ),
      '116.utf8-backup-first-expect-wait.sql' => array(
        'type'    => 'sql',
        'name'    =>
          $this->getPatchPath('116.utf8-backup-first-expect-wait.sql'),
        'legacy'  => 116,
      ),
      '117.repositorydescription.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('117.repositorydescription.php'),
        'legacy'  => 117,
      ),
      '118.auditinline.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('118.auditinline.sql'),
        'legacy'  => 118,
      ),
      '119.filehash.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('119.filehash.sql'),
        'legacy'  => 119,
      ),
      '120.noop.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('120.noop.sql'),
        'legacy'  => 120,
      ),
      '121.drydocklog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('121.drydocklog.sql'),
        'legacy'  => 121,
      ),
      '122.flag.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('122.flag.sql'),
        'legacy'  => 122,
      ),
      '123.heraldrulelog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('123.heraldrulelog.sql'),
        'legacy'  => 123,
      ),
      '124.subpriority.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('124.subpriority.sql'),
        'legacy'  => 124,
      ),
      '125.ipv6.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('125.ipv6.sql'),
        'legacy'  => 125,
      ),
      '126.edges.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('126.edges.sql'),
        'legacy'  => 126,
      ),
      '127.userkeybody.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('127.userkeybody.sql'),
        'legacy'  => 127,
      ),
      '128.phabricatorcom.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('128.phabricatorcom.sql'),
        'legacy'  => 128,
      ),
      '129.savedquery.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('129.savedquery.sql'),
        'legacy'  => 129,
      ),
      '130.denormalrevisionquery.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('130.denormalrevisionquery.sql'),
        'legacy'  => 130,
      ),
      '131.migraterevisionquery.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('131.migraterevisionquery.php'),
        'legacy'  => 131,
      ),
      '132.phame.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('132.phame.sql'),
        'legacy'  => 132,
      ),
      '133.imagemacro.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('133.imagemacro.sql'),
        'legacy'  => 133,
      ),
      '134.emptysearch.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('134.emptysearch.sql'),
        'legacy'  => 134,
      ),
      '135.datecommitted.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('135.datecommitted.sql'),
        'legacy'  => 135,
      ),
      '136.sex.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('136.sex.sql'),
        'legacy'  => 136,
      ),
      '137.auditmetadata.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('137.auditmetadata.sql'),
        'legacy'  => 137,
      ),
      '138.notification.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('138.notification.sql'),
      ),
      'holidays.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('holidays.sql'),
      ),
      'userstatus.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('userstatus.sql'),
      ),
      'emailtable.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('emailtable.sql'),
      ),
      'emailtableport.sql' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('emailtableport.php'),
      ),
      'emailtableremove.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('emailtableremove.sql'),
      ),
      'phiddrop.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('phiddrop.sql'),
      ),
      'testdatabase.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('testdatabase.sql'),
      ),
      'ldapinfo.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('ldapinfo.sql'),
      ),
      'threadtopic.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('threadtopic.sql'),
      ),
      'usertranslation.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('usertranslation.sql'),
      ),
      'differentialbookmarks.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('differentialbookmarks.sql'),
      ),
      'harbormasterobject.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('harbormasterobject.sql'),
      ),
      'markupcache.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('markupcache.sql'),
      ),
      'maniphestxcache.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('maniphestxcache.sql'),
      ),
      'migrate-maniphest-dependencies.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('migrate-maniphest-dependencies.php'),
      ),
      'migrate-differential-dependencies.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath(
          'migrate-differential-dependencies.php'),
      ),
      'phameblog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('phameblog.sql'),
      ),
      'migrate-maniphest-revisions.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('migrate-maniphest-revisions.php'),
      ),
      'daemonstatus.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('daemonstatus.sql'),
      ),
      'symbolcontexts.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('symbolcontexts.sql'),
      ),
      'migrate-project-edges.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('migrate-project-edges.php'),
      ),
      'fact-raw.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('fact-raw.sql'),
      ),
      'ponder.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('ponder.sql')
      ),
      'policy-project.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('policy-project.sql'),
      ),
      'daemonstatuskey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('daemonstatuskey.sql'),
      ),
      'edgetype.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('edgetype.sql'),
      ),
      'ponder-comments.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('ponder-comments.sql'),
      ),
      'pastepolicy.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('pastepolicy.sql'),
      ),
      'xhprof.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('xhprof.sql'),
      ),
      'draft-metadata.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('draft-metadata.sql'),
      ),
      'phamedomain.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('phamedomain.sql'),
      ),
      'ponder-mailkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('ponder-mailkey.sql'),
      ),
      'ponder-mailkey-populate.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('ponder-mailkey-populate.php'),
      ),
      'phamepolicy.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('phamepolicy.sql'),
      ),
      'phameoneblog.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('phameoneblog.sql'),
      ),
      'statustxt.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('statustxt.sql'),
      ),
      'daemontaskarchive.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('daemontaskarchive.sql'),
      ),
      'drydocktaskid.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('drydocktaskid.sql'),
      ),
      'drydockresoucetype.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('drydockresourcetype.sql'),
      ),
      'liskcounters.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('liskcounters.sql'),
      ),
      'liskcounters.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('liskcounters.php'),
      ),
      'dropfileproxyimage.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('dropfileproxyimage.sql'),
      ),
      'repository-lint.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('repository-lint.sql'),
      ),
      'liskcounters-task.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('liskcounters-task.sql'),
      ),
      'pholio.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('pholio.sql'),
      ),
      'owners-exclude.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('owners-exclude.sql'),
      ),
      '20121209.pholioxactions.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20121209.pholioxactions.sql'),
      ),
      '20121209.xmacroadd.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20121209.xmacroadd.sql'),
      ),
      '20121209.xmacromigrate.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('20121209.xmacromigrate.php'),
      ),
      '20121209.xmacromigratekey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20121209.xmacromigratekey.sql'),
      ),
      '20121220.generalcache.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20121220.generalcache.sql'),
      ),
      '20121226.config.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20121226.config.sql'),
      ),
      '20130101.confxaction.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130101.confxaction.sql'),
      ),
      '20130102.metamtareceivedmailmessageidhash.sql' => array(
        'type'    => 'sql',
        'name'    =>
          $this->getPatchPath('20130102.metamtareceivedmailmessageidhash.sql'),
      ),
      '20130103.filemetadata.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130103.filemetadata.sql'),
      ),
      '20130111.conpherence.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130111.conpherence.sql'),
      ),
      '20130127.altheraldtranscript.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130127.altheraldtranscript.sql'),
      ),
      '20130201.revisionunsubscribed.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('20130201.revisionunsubscribed.php'),
      ),
      '20130201.revisionunsubscribed.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130201.revisionunsubscribed.sql'),
      ),
      '20130131.conpherencepics.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130131.conpherencepics.sql'),
      ),
      '20130214.chatlogchannel.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130214.chatlogchannel.sql'),
      ),
      '20130214.chatlogchannelid.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130214.chatlogchannelid.sql'),
      ),
      '20130214.token.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130214.token.sql'),
      ),
      '20130215.phabricatorfileaddttl.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130215.phabricatorfileaddttl.sql'),
      ),
      '20130217.cachettl.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130217.cachettl.sql'),
      ),
      '20130218.updatechannelid.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('20130218.updatechannelid.php'),
      ),
      '20130218.longdaemon.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130218.longdaemon.sql'),
      ),
      '20130219.commitsummary.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130219.commitsummary.sql'),
      ),
      '20130219.commitsummarymig.php' => array(
        'type'    => 'php',
        'name'    => $this->getPatchPath('20130219.commitsummarymig.php'),
      ),
      '20130222.dropchannel.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130222.dropchannel.sql'),
      ),
      '20130226.commitkey.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130226.commitkey.sql'),
      ),
      '20131302.maniphestvalue.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20131302.maniphestvalue.sql'),
      ),
      '20130304.lintauthor.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130304.lintauthor.sql'),
      ),
      'releeph.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('releeph.sql'),
      ),
      '20130319.phabricatorfileexplicitupload.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath(
          '20130319.phabricatorfileexplicitupload.sql')
      ),
      '20130319.conpherence.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130319.conpherence.sql'),
      ),
      '20130320.phlux.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130320.phlux.sql'),
      ),
      '20130317.phrictionedge.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130317.phrictionedge.sql'),
      ),
      '20130321.token.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130321.token.sql'),
      ),
      '20130310.xactionmeta.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130310.xactionmeta.sql'),
      ),
      '20130322.phortune.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130322.phortune.sql'),
      ),
      '20130323.phortunepayment.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130323.phortunepayment.sql'),
      ),
      '20130324.phortuneproduct.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130324.phortuneproduct.sql'),
      ),
      '20130330.phrequent.sql' => array(
        'type'    => 'sql',
        'name'    => $this->getPatchPath('20130330.phrequent.sql'),
      ),
      '20130403.conpherencecache.sql' => array(
        'type' => 'sql',
        'name' => $this->getPatchPath('20130403.conpherencecache.sql'),
      ),
      '20130403.conpherencecachemig.php' => array(
        'type' => 'php',
        'name' => $this->getPatchPath('20130403.conpherencecachemig.php'),
      ),
      '20130409.commitdrev.php' => array(
        'type' => 'php',
        'name' => $this->getPatchPath('20130409.commitdrev.php'),
      ),
    );
  }
}
