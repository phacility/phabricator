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
    $patches = array();

    foreach ($this->getOldPatches() as $old_name => $old_patch) {
      if (preg_match('/^db\./', $old_name)) {
        $old_patch['name'] = substr($old_name, 3);
        $old_patch['type'] = 'db';
      } else {
        if (empty($old_patch['name'])) {
          $old_patch['name'] = $this->getPatchPath($old_name);
        }
        if (empty($old_patch['type'])) {
          $matches = null;
          preg_match('/\.(sql|php)$/', $old_name, $matches);
          $old_patch['type'] = $matches[1];
        }
      }

      $patches[$old_name] = $old_patch;
    }

    $root = dirname(phutil_get_library_root('phabricator'));
    $auto_root = $root.'/resources/sql/autopatches/';
    $patches += $this->buildPatchesFromDirectory($auto_root);

    return $patches;
  }

  public function getOldPatches() {
    return array(
      'db.audit' => array(
        'after' => array( /* First Patch */ ),
      ),
      'db.calendar' => array(),
      'db.chatlog' => array(),
      'db.conduit' => array(),
      'db.countdown' => array(),
      'db.daemon' => array(),
      'db.differential' => array(),
      'db.draft' => array(),
      'db.drydock' => array(),
      'db.feed' => array(),
      'db.file' => array(),
      'db.flag' => array(),
      'db.harbormaster' => array(),
      'db.herald' => array(),
      'db.maniphest' => array(),
      'db.meta_data' => array(),
      'db.metamta' => array(),
      'db.oauth_server' => array(),
      'db.owners' => array(),
      'db.pastebin' => array(),
      'db.phame' => array(),
      'db.phriction' => array(),
      'db.project' => array(),
      'db.repository' => array(),
      'db.search' => array(),
      'db.slowvote' => array(),
      'db.timeline' => array(
        'dead' => true,
      ),
      'db.user' => array(),
      'db.worker' => array(),
      'db.xhpast' => array(),
      'db.xhpastview' => array(
        'dead' => true,
      ),
      'db.cache' => array(),
      'db.fact' => array(),
      'db.ponder' => array(),
      'db.xhprof' => array(),
      'db.pholio' => array(),
      'db.conpherence' => array(),
      'db.config' => array(),
      'db.token' => array(),
      'db.releeph' => array(),
      'db.phlux' => array(),
      'db.phortune' => array(),
      'db.phrequent' => array(),
      'db.diviner' => array(),
      'db.auth' => array(),
      'db.doorkeeper' => array(),
      'db.legalpad' => array(),
      'db.policy' => array(),
      'db.nuance' => array(),
      'db.passphrase' => array(),
      'db.phragment' => array(),
      'db.dashboard' => array(),
      'db.system' => array(),
      'db.fund' => array(),
      'db.almanac' => array(),
      'db.multimeter' => array(),
      'db.spaces' => array(),
      'db.phurl' => array(),
      'db.badges' => array(),
      '0000.legacy.sql' => array(
        'legacy' => 0,
      ),
      '000.project.sql' => array(
        'legacy' => 0,
      ),
      '001.maniphest_projects.sql' => array(
        'legacy' => 1,
      ),
      '002.oauth.sql' => array(
        'legacy' => 2,
      ),
      '003.more_oauth.sql' => array(
        'legacy' => 3,
      ),
      '004.daemonrepos.sql' => array(
        'legacy' => 4,
      ),
      '005.workers.sql' => array(
        'legacy' => 5,
      ),
      '006.repository.sql' => array(
        'legacy' => 6,
      ),
      '007.daemonlog.sql' => array(
        'legacy' => 7,
      ),
      '008.repoopt.sql' => array(
        'legacy' => 8,
      ),
      '009.repo_summary.sql' => array(
        'legacy' => 9,
      ),
      '010.herald.sql' => array(
        'legacy' => 10,
      ),
      '011.badcommit.sql' => array(
        'legacy' => 11,
      ),
      '012.dropphidtype.sql' => array(
        'legacy' => 12,
      ),
      '013.commitdetail.sql' => array(
        'legacy' => 13,
      ),
      '014.shortcuts.sql' => array(
        'legacy' => 14,
      ),
      '015.preferences.sql' => array(
        'legacy' => 15,
      ),
      '016.userrealnameindex.sql' => array(
        'legacy' => 16,
      ),
      '017.sessionkeys.sql' => array(
        'legacy' => 17,
      ),
      '018.owners.sql' => array(
        'legacy' => 18,
      ),
      '019.arcprojects.sql' => array(
        'legacy' => 19,
      ),
      '020.pathcapital.sql' => array(
        'legacy' => 20,
      ),
      '021.xhpastview.sql' => array(
        'legacy' => 21,
      ),
      '022.differentialcommit.sql' => array(
        'legacy' => 22,
      ),
      '023.dxkeys.sql' => array(
        'legacy' => 23,
      ),
      '024.mlistkeys.sql' => array(
        'legacy' => 24,
      ),
      '025.commentopt.sql' => array(
        'legacy' => 25,
      ),
      '026.diffpropkey.sql' => array(
        'legacy' => 26,
      ),
      '027.metamtakeys.sql' => array(
        'legacy' => 27,
      ),
      '028.systemagent.sql' => array(
        'legacy' => 28,
      ),
      '029.cursors.sql' => array(
        'legacy' => 29,
      ),
      '030.imagemacro.sql' => array(
        'legacy' => 30,
      ),
      '031.workerrace.sql' => array(
        'legacy' => 31,
      ),
      '032.viewtime.sql' => array(
        'legacy' => 32,
      ),
      '033.privtest.sql' => array(
        'legacy' => 33,
      ),
      '034.savedheader.sql' => array(
        'legacy' => 34,
      ),
      '035.proxyimage.sql' => array(
        'legacy' => 35,
      ),
      '036.mailkey.sql' => array(
        'legacy' => 36,
      ),
      '037.setuptest.sql' => array(
        'legacy' => 37,
      ),
      '038.admin.sql' => array(
        'legacy' => 38,
      ),
      '039.userlog.sql' => array(
        'legacy' => 39,
      ),
      '040.transform.sql' => array(
        'legacy' => 40,
      ),
      '041.heraldrepetition.sql' => array(
        'legacy' => 41,
      ),
      '042.commentmetadata.sql' => array(
        'legacy' => 42,
      ),
      '043.pastebin.sql' => array(
        'legacy' => 43,
      ),
      '044.countdown.sql' => array(
        'legacy' => 44,
      ),
      '045.timezone.sql' => array(
        'legacy' => 45,
      ),
      '046.conduittoken.sql' => array(
        'legacy' => 46,
      ),
      '047.projectstatus.sql' => array(
        'legacy' => 47,
      ),
      '048.relationshipkeys.sql' => array(
        'legacy' => 48,
      ),
      '049.projectowner.sql' => array(
        'legacy' => 49,
      ),
      '050.taskdenormal.sql' => array(
        'legacy' => 50,
      ),
      '051.projectfilter.sql' => array(
        'legacy' => 51,
      ),
      '052.pastelanguage.sql' => array(
        'legacy' => 52,
      ),
      '053.feed.sql' => array(
        'legacy' => 53,
      ),
      '054.subscribers.sql' => array(
        'legacy' => 54,
      ),
      '055.add_author_to_files.sql' => array(
        'legacy' => 55,
      ),
      '056.slowvote.sql' => array(
        'legacy' => 56,
      ),
      '057.parsecache.sql' => array(
        'legacy' => 57,
      ),
      '058.missingkeys.sql' => array(
        'legacy' => 58,
      ),
      '059.engines.php' => array(
        'legacy' => 59,
      ),
      '060.phriction.sql' => array(
        'legacy' => 60,
      ),
      '061.phrictioncontent.sql' => array(
        'legacy' => 61,
      ),
      '062.phrictionmenu.sql' => array(
        'legacy' => 62,
      ),
      '063.pasteforks.sql' => array(
        'legacy' => 63,
      ),
      '064.subprojects.sql' => array(
        'legacy' => 64,
      ),
      '065.sshkeys.sql' => array(
        'legacy' => 65,
      ),
      '066.phrictioncontent.sql' => array(
        'legacy' => 66,
      ),
      '067.preferences.sql' => array(
        'legacy' => 67,
      ),
      '068.maniphestauxiliarystorage.sql' => array(
        'legacy' => 68,
      ),
      '069.heraldxscript.sql' => array(
        'legacy' => 69,
      ),
      '070.differentialaux.sql' => array(
        'legacy' => 70,
      ),
      '071.contentsource.sql' => array(
        'legacy' => 71,
      ),
      '072.blamerevert.sql' => array(
        'legacy' => 72,
      ),
      '073.reposymbols.sql' => array(
        'legacy' => 73,
      ),
      '074.affectedpath.sql' => array(
        'legacy' => 74,
      ),
      '075.revisionhash.sql' => array(
        'legacy' => 75,
      ),
      '076.indexedlanguages.sql' => array(
        'legacy' => 76,
      ),
      '077.originalemail.sql' => array(
        'legacy' => 77,
      ),
      '078.nametoken.sql' => array(
        'legacy' => 78,
      ),
      '079.nametokenindex.php' => array(
        'legacy' => 79,
      ),
      '080.filekeys.sql' => array(
        'legacy' => 80,
      ),
      '081.filekeys.php' => array(
        'legacy' => 81,
      ),
      '082.xactionkey.sql' => array(
        'legacy' => 82,
      ),
      '083.dxviewtime.sql' => array(
        'legacy' => 83,
      ),
      '084.pasteauthorkey.sql' => array(
        'legacy' => 84,
      ),
      '085.packagecommitrelationship.sql' => array(
        'legacy' => 85,
      ),
      '086.formeraffil.sql' => array(
        'legacy' => 86,
      ),
      '087.phrictiondelete.sql' => array(
        'legacy' => 87,
      ),
      '088.audit.sql' => array(
        'legacy' => 88,
      ),
      '089.projectwiki.sql' => array(
        'legacy' => 89,
      ),
      '090.forceuniqueprojectnames.php' => array(
        'legacy' => 90,
      ),
      '091.uniqueslugkey.sql' => array(
        'legacy' => 91,
      ),
      '092.dropgithubnotification.sql' => array(
        'legacy' => 92,
      ),
      '093.gitremotes.php' => array(
        'legacy' => 93,
      ),
      '094.phrictioncolumn.sql' => array(
        'legacy' => 94,
      ),
      '095.directory.sql' => array(
        'legacy' => 95,
      ),
      '096.filename.sql' => array(
        'legacy' => 96,
      ),
      '097.heraldruletypes.sql' => array(
        'legacy' => 97,
      ),
      '098.heraldruletypemigration.php' => array(
        'legacy' => 98,
      ),
      '099.drydock.sql' => array(
        'legacy' => 99,
      ),
      '100.projectxaction.sql' => array(
        'legacy' => 100,
      ),
      '101.heraldruleapplied.sql' => array(
        'legacy' => 101,
      ),
      '102.heraldcleanup.php' => array(
        'legacy' => 102,
      ),
      '103.heraldedithistory.sql' => array(
        'legacy' => 103,
      ),
      '104.searchkey.sql' => array(
        'legacy' => 104,
      ),
      '105.mimetype.sql' => array(
        'legacy' => 105,
      ),
      '106.chatlog.sql' => array(
        'legacy' => 106,
      ),
      '107.oauthserver.sql' => array(
        'legacy' => 107,
      ),
      '108.oauthscope.sql' => array(
        'legacy' => 108,
      ),
      '109.oauthclientphidkey.sql' => array(
        'legacy' => 109,
      ),
      '110.commitaudit.sql' => array(
        'legacy' => 110,
      ),
      '111.commitauditmigration.php' => array(
        'legacy' => 111,
      ),
      '112.oauthaccesscoderedirecturi.sql' => array(
        'legacy' => 112,
      ),
      '113.lastreviewer.sql' => array(
        'legacy' => 113,
      ),
      '114.auditrequest.sql' => array(
        'legacy' => 114,
      ),
      '115.prepareutf8.sql' => array(
        'legacy' => 115,
      ),
      '116.utf8-backup-first-expect-wait.sql' => array(
        'legacy' => 116,
      ),
      '117.repositorydescription.php' => array(
        'legacy' => 117,
      ),
      '118.auditinline.sql' => array(
        'legacy' => 118,
      ),
      '119.filehash.sql' => array(
        'legacy' => 119,
      ),
      '120.noop.sql' => array(
        'legacy' => 120,
      ),
      '121.drydocklog.sql' => array(
        'legacy' => 121,
      ),
      '122.flag.sql' => array(
        'legacy' => 122,
      ),
      '123.heraldrulelog.sql' => array(
        'legacy' => 123,
      ),
      '124.subpriority.sql' => array(
        'legacy' => 124,
      ),
      '125.ipv6.sql' => array(
        'legacy' => 125,
      ),
      '126.edges.sql' => array(
        'legacy' => 126,
      ),
      '127.userkeybody.sql' => array(
        'legacy' => 127,
      ),
      '128.phabricatorcom.sql' => array(
        'legacy' => 128,
      ),
      '129.savedquery.sql' => array(
        'legacy' => 129,
      ),
      '130.denormalrevisionquery.sql' => array(
        'legacy' => 130,
      ),
      '131.migraterevisionquery.php' => array(
        'legacy' => 131,
      ),
      '132.phame.sql' => array(
        'legacy' => 132,
      ),
      '133.imagemacro.sql' => array(
        'legacy' => 133,
      ),
      '134.emptysearch.sql' => array(
        'legacy' => 134,
      ),
      '135.datecommitted.sql' => array(
        'legacy' => 135,
      ),
      '136.sex.sql' => array(
        'legacy' => 136,
      ),
      '137.auditmetadata.sql' => array(
        'legacy' => 137,
      ),
      '138.notification.sql' => array(),
      'holidays.sql' => array(),
      'userstatus.sql' => array(),
      'emailtable.sql' => array(),
      'emailtableport.sql' => array(
        // NOTE: This is a ".php" patch, but the key is ".sql".
        'type' => 'php',
        'name' => $this->getPatchPath('emailtableport.php'),
      ),
      'emailtableremove.sql' => array(),
      'phiddrop.sql' => array(),
      'testdatabase.sql' => array(),
      'ldapinfo.sql' => array(),
      'threadtopic.sql' => array(),
      'usertranslation.sql' => array(),
      'differentialbookmarks.sql' => array(),
      'harbormasterobject.sql' => array(),
      'markupcache.sql' => array(),
      'maniphestxcache.sql' => array(),
      'migrate-maniphest-dependencies.php' => array(),
      'migrate-differential-dependencies.php' => array(),
      'phameblog.sql' => array(),
      'migrate-maniphest-revisions.php' => array(),
      'daemonstatus.sql' => array(),
      'symbolcontexts.sql' => array(),
      'migrate-project-edges.php' => array(),
      'fact-raw.sql' => array(),
      'ponder.sql' => array(),
      'policy-project.sql' => array(),
      'daemonstatuskey.sql' => array(),
      'edgetype.sql' => array(),
      'ponder-comments.sql' => array(),
      'pastepolicy.sql' => array(),
      'xhprof.sql' => array(),
      'draft-metadata.sql' => array(),
      'phamedomain.sql' => array(),
      'ponder-mailkey.sql' => array(),
      'ponder-mailkey-populate.php' => array(),
      'phamepolicy.sql' => array(),
      'phameoneblog.sql' => array(),
      'statustxt.sql' => array(),
      'daemontaskarchive.sql' => array(),
      'drydocktaskid.sql' => array(),
      'drydockresoucetype.sql' => array(
        // NOTE: The key for this patch misspells "resource" as "resouce".
        'name' => $this->getPatchPath('drydockresourcetype.sql'),
      ),
      'liskcounters.sql' => array(),
      'liskcounters.php' => array(),
      'dropfileproxyimage.sql' => array(),
      'repository-lint.sql' => array(),
      'liskcounters-task.sql' => array(),
      'pholio.sql' => array(),
      'owners-exclude.sql' => array(),
      '20121209.pholioxactions.sql' => array(),
      '20121209.xmacroadd.sql' => array(),
      '20121209.xmacromigrate.php' => array(),
      '20121209.xmacromigratekey.sql' => array(),
      '20121220.generalcache.sql' => array(),
      '20121226.config.sql' => array(),
      '20130101.confxaction.sql' => array(),
      '20130102.metamtareceivedmailmessageidhash.sql' => array(),
      '20130103.filemetadata.sql' => array(),
      '20130111.conpherence.sql' => array(),
      '20130127.altheraldtranscript.sql' => array(),
      '20130201.revisionunsubscribed.php' => array(),
      '20130201.revisionunsubscribed.sql' => array(),
      '20130131.conpherencepics.sql' => array(),
      '20130214.chatlogchannel.sql' => array(),
      '20130214.chatlogchannelid.sql' => array(),
      '20130214.token.sql' => array(),
      '20130215.phabricatorfileaddttl.sql' => array(),
      '20130217.cachettl.sql' => array(),
      '20130218.updatechannelid.php' => array(),
      '20130218.longdaemon.sql' => array(),
      '20130219.commitsummary.sql' => array(),
      '20130219.commitsummarymig.php' => array(),
      '20130222.dropchannel.sql' => array(),
      '20130226.commitkey.sql' => array(),
      '20131302.maniphestvalue.sql' => array(),
      '20130304.lintauthor.sql' => array(),
      'releeph.sql' => array(),
      '20130319.phabricatorfileexplicitupload.sql' => array(),
      '20130319.conpherence.sql' => array(),
      '20130320.phlux.sql' => array(),
      '20130317.phrictionedge.sql' => array(),
      '20130321.token.sql' => array(),
      '20130310.xactionmeta.sql' => array(),
      '20130322.phortune.sql' => array(),
      '20130323.phortunepayment.sql' => array(),
      '20130324.phortuneproduct.sql' => array(),
      '20130330.phrequent.sql' => array(),
      '20130403.conpherencecache.sql' => array(),
      '20130403.conpherencecachemig.php' => array(),
      '20130409.commitdrev.php' => array(),
      '20130417.externalaccount.sql' => array(),
      '20130423.updateexternalaccount.sql' => array(),
      '20130423.phortunepaymentrevised.sql' => array(),
      '20130423.conpherenceindices.sql' => array(),
      '20130426.search_savedquery.sql' => array(),
      '20130502.countdownrevamp1.sql' => array(),
      '20130502.countdownrevamp2.php' => array(),
      '20130502.countdownrevamp3.sql' => array(),
      '20130507.releephrqsimplifycols.sql' => array(),
      '20130507.releephrqmailkey.sql' => array(),
      '20130507.releephrqmailkeypop.php' => array(),
      '20130508.search_namedquery.sql' => array(),
      '20130508.releephtransactions.sql' => array(),
      '20130508.releephtransactionsmig.php' => array(),
      '20130513.receviedmailstatus.sql' => array(),
      '20130519.diviner.sql' => array(),
      '20130521.dropconphimages.sql' => array(),
      '20130523.maniphest_owners.sql' => array(),
      '20130524.repoxactions.sql' => array(),
      '20130529.macroauthor.sql' => array(),
      '20130529.macroauthormig.php' => array(),
      '20130530.sessionhash.php' => array(),
      '20130530.macrodatekey.sql' => array(),
      '20130530.pastekeys.sql' => array(),
      '20130531.filekeys.sql' => array(),
      '20130602.morediviner.sql' => array(),
      '20130602.namedqueries.sql' => array(),
      '20130606.userxactions.sql' => array(),
      '20130607.xaccount.sql' => array(),
      '20130611.migrateoauth.php' => array(),
      '20130611.nukeldap.php' => array(),
      '20130613.authdb.sql' => array(),
      '20130619.authconf.php' => array(),
      '20130620.diffxactions.sql' => array(),
      '20130621.diffcommentphid.sql' => array(),
      '20130621.diffcommentphidmig.php' => array(),
      '20130621.diffcommentunphid.sql' => array(),
      '20130622.doorkeeper.sql' => array(),
      '20130628.legalpadv0.sql' => array(),
      '20130701.conduitlog.sql' => array(),
      'legalpad-mailkey.sql' => array(),
      'legalpad-mailkey-populate.php' => array(),
      '20130703.legalpaddocdenorm.sql' => array(),
      '20130703.legalpaddocdenorm.php' => array(),
      '20130709.legalpadsignature.sql' => array(),
      '20130709.droptimeline.sql' => array(),
      '20130711.trimrealnames.php' => array(),
      '20130714.votexactions.sql' => array(),
      '20130715.votecomments.php' => array(),
      '20130715.voteedges.sql' => array(),
      '20130711.pholioimageobsolete.sql' => array(),
      '20130711.pholioimageobsolete.php' => array(),
      '20130711.pholioimageobsolete2.sql' => array(),
      '20130716.archivememberlessprojects.php' => array(),
      '20130722.pholioreplace.sql' => array(),
      '20130723.taskstarttime.sql' => array(),
      '20130727.ponderquestionstatus.sql' => array(),
      '20130726.ponderxactions.sql' => array(),
      '20130728.ponderunique.php' => array(),
      '20130728.ponderuniquekey.sql' => array(),
      '20130728.ponderxcomment.php' => array(),
      '20130801.pastexactions.sql' => array(),
      '20130801.pastexactions.php' => array(),
      '20130805.pastemailkey.sql' => array(),
      '20130805.pasteedges.sql' => array(),
      '20130805.pastemailkeypop.php' => array(),
      '20130802.heraldphid.sql' => array(),
      '20130802.heraldphids.php' => array(),
      '20130802.heraldphidukey.sql' => array(),
      '20130802.heraldxactions.sql' => array(),
      '20130731.releephrepoid.sql' => array(),
      '20130731.releephproject.sql' => array(),
      '20130731.releephcutpointidentifier.sql' => array(),
      '20130814.usercustom.sql' => array(),
      '20130820.releephxactions.sql' => array(),
      '20130826.divinernode.sql' => array(),
      '20130820.filexactions.sql' => array(),
      '20130820.filemailkey.sql' => array(),
      '20130820.file-mailkey-populate.php' => array(),
      '20130912.maniphest.1.touch.sql' => array(),
      '20130912.maniphest.2.created.sql' => array(),
      '20130912.maniphest.3.nameindex.sql' => array(),
      '20130912.maniphest.4.fillindex.php' => array(),
      '20130913.maniphest.1.migratesearch.php' => array(),
      '20130914.usercustom.sql' => array(),
      '20130915.maniphestcustom.sql' => array(),
      '20130915.maniphestmigrate.php' => array(),
      '20130919.mfieldconf.php' => array(),
      '20130920.repokeyspolicy.sql' => array(),
      '20130921.mtransactions.sql' => array(),
      '20130921.xmigratemaniphest.php' => array(),
      '20130923.mrename.sql' => array(),
      '20130924.mdraftkey.sql' => array(),
      '20130925.mpolicy.sql' => array(),
      '20130925.xpolicy.sql' => array(),
      '20130926.dcustom.sql' => array(),
      '20130926.dinkeys.sql' => array(),
      '20130927.audiomacro.sql' => array(),
      '20130929.filepolicy.sql' => array(),
      '20131004.dxedgekey.sql' => array(),
      '20131004.dxreviewers.php' => array(),
      '20131006.hdisable.sql' => array(),
      '20131010.pstorage.sql' => array(),
      '20131015.cpolicy.sql' => array(),
      '20130915.maniphestqdrop.sql' => array(),
      '20130926.dinline.php' => array(),
      '20131020.pcustom.sql' => array(),
      '20131020.col1.sql' => array(),
      '20131020.pxaction.sql' => array(),
      '20131020.pxactionmig.php' => array(),
      '20131020.harbormaster.sql' => array(),
      '20131025.repopush.sql' => array(),
      '20131026.commitstatus.sql' => array(),
      '20131030.repostatusmessage.sql' => array(),
      '20131031.vcspassword.sql' => array(),
      '20131105.buildstep.sql' => array(),
      '20131106.diffphid.1.col.sql' => array(),
      '20131106.diffphid.2.mig.php' => array(),
      '20131106.diffphid.3.key.sql' => array(),
      '20131106.nuance-v0.sql' => array(),
      '20131107.buildlog.sql' => array(),
      '20131112.userverified.1.col.sql' => array(),
      '20131112.userverified.2.mig.php' => array(),
      '20131118.ownerorder.php' => array(),
      '20131119.passphrase.sql' => array(),
      '20131120.nuancesourcetype.sql' => array(),
      '20131121.passphraseedge.sql' => array(),
      '20131121.repocredentials.1.col.sql' => array(),
      '20131121.repocredentials.2.mig.php' => array(),
      '20131122.repomirror.sql' => array(),
      '20131123.drydockblueprintpolicy.sql' => array(),
      '20131129.drydockresourceblueprint.sql' => array(),
      '20131205.buildtargets.sql' => array(),
      '20131204.pushlog.sql' => array(),
      '20131205.buildsteporder.sql' => array(),
      '20131205.buildstepordermig.php' => array(),
      '20131206.phragment.sql' => array(),
      '20131206.phragmentnull.sql' => array(),
      '20131208.phragmentsnapshot.sql' => array(),
      '20131211.phragmentedges.sql' => array(),
      '20131217.pushlogphid.1.col.sql' => array(),
      '20131217.pushlogphid.2.mig.php' => array(),
      '20131217.pushlogphid.3.key.sql' => array(),
      '20131219.pxdrop.sql' => array(),
      '20131224.harbormanual.sql' => array(),
      '20131227.heraldobject.sql' => array(),
      '20131231.dropshortcut.sql' => array(),
    );

    // NOTE: STOP! Don't add new patches here.
    // Use 'resources/sql/autopatches/' instead!
  }
}
