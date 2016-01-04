<?php

final class PhabricatorPasteFilenameContextFreeGrammar
  extends PhutilContextFreeGrammar {

  protected function getRules() {
    return array(
      'start' => array(
        '[scripty]',
      ),
      'scripty' => array(
        '[thing]',
        '[thing]',
        '[thing]_[tail]',
        '[action]_[thing]',
        '[action]_[thing]',
        '[action]_[thing]_[tail]',
        '[scripty]_and_[scripty]',
      ),
      'tail' => array(
        'script',
        'helper',
        'backup',
        'pro',
        '[tail]_[tail]',
      ),
      'thing' => array(
        '[thingnoun]',
        '[thingadjective]_[thingnoun]',
        '[thingadjective]_[thingadjective]_[thingnoun]',
      ),
      'thingnoun' => array(
        'backup',
        'backups',
        'database',
        'databases',
        'table',
        'tables',
        'memory',
        'disk',
        'disks',
        'user',
        'users',
        'account',
        'accounts',
        'shard',
        'shards',
        'node',
        'nodes',
        'host',
        'hosts',
        'account',
        'accounts',
      ),
      'thingadjective' => array(
        'backup',
        'database',
        'memory',
        'disk',
        'user',
        'account',
        'forgotten',
        'lost',
        'elder',
        'ancient',
        'legendary',
      ),
      'action' => array(
        'manage',
        'update',
        'compact',
        'quick',
        'probe',
        'sync',
        'undo',
        'administrate',
        'assess',
        'purge',
        'cancel',
        'entomb',
        'accelerate',
        'plan',
      ),
    );
  }

}
