<?php

/**
 * Simple object-authoritative data access object that makes it easy to build
 * stuff that you need to save to a database. Basically, it means that the
 * amount of boilerplate code (and, particularly, boilerplate SQL) you need
 * to write is greatly reduced.
 *
 * Lisk makes it fairly easy to build something quickly and end up with
 * reasonably high-quality code when you're done (e.g., getters and setters,
 * objects, transactions, reasonably structured OO code). It's also very thin:
 * you can break past it and use MySQL and other lower-level tools when you
 * need to in those couple of cases where it doesn't handle your workflow
 * gracefully.
 *
 * However, Lisk won't scale past one database and lacks many of the features
 * of modern DAOs like Hibernate: for instance, it does not support joins or
 * polymorphic storage.
 *
 * This means that Lisk is well-suited for tools like Differential, but often a
 * poor choice elsewhere. And it is strictly unsuitable for many projects.
 *
 * Lisk's model is object-authoritative: the PHP class definition is the
 * master authority for what the object looks like.
 *
 * =Building New Objects=
 *
 * To create new Lisk objects, extend @{class:LiskDAO} and implement
 * @{method:establishLiveConnection}. It should return an
 * @{class:AphrontDatabaseConnection}; this will tell Lisk where to save your
 * objects.
 *
 *   class Dog extends LiskDAO {
 *
 *     protected $name;
 *     protected $breed;
 *
 *     public function establishLiveConnection() {
 *       return $some_connection_object;
 *     }
 *   }
 *
 * Now, you should create your table:
 *
 *   lang=sql
 *   CREATE TABLE dog (
 *     id int unsigned not null auto_increment primary key,
 *     name varchar(32) not null,
 *     breed varchar(32) not null,
 *     dateCreated int unsigned not null,
 *     dateModified int unsigned not null
 *   );
 *
 * For each property in your class, add a column with the same name to the table
 * (see @{method:getConfiguration} for information about changing this mapping).
 * Additionally, you should create the three columns `id`,  `dateCreated` and
 * `dateModified`. Lisk will automatically manage these, using them to implement
 * autoincrement IDs and timestamps. If you do not want to use these features,
 * see @{method:getConfiguration} for information on disabling them. At a bare
 * minimum, you must normally have an `id` column which is a primary or unique
 * key with a numeric type, although you can change its name by overriding
 * @{method:getIDKey} or disable it entirely by overriding @{method:getIDKey} to
 * return null. Note that many methods rely on a single-part primary key and
 * will no longer work (they will throw) if you disable it.
 *
 * As you add more properties to your class in the future, remember to add them
 * to the database table as well.
 *
 * Lisk will now automatically handle these operations: getting and setting
 * properties, saving objects, loading individual objects, loading groups
 * of objects, updating objects, managing IDs, updating timestamps whenever
 * an object is created or modified, and some additional specialized
 * operations.
 *
 * = Creating, Retrieving, Updating, and Deleting =
 *
 * To create and persist a Lisk object, use @{method:save}:
 *
 *   $dog = id(new Dog())
 *     ->setName('Sawyer')
 *     ->setBreed('Pug')
 *     ->save();
 *
 * Note that **Lisk automatically builds getters and setters for all of your
 * object's protected properties** via @{method:__call}. If you want to add
 * custom behavior to your getters or setters, you can do so by overriding the
 * @{method:readField} and @{method:writeField} methods.
 *
 * Calling @{method:save} will persist the object to the database. After calling
 * @{method:save}, you can call @{method:getID} to retrieve the object's ID.
 *
 * To load objects by ID, use the @{method:load} method:
 *
 *   $dog = id(new Dog())->load($id);
 *
 * This will load the Dog record with ID $id into $dog, or `null` if no such
 * record exists (@{method:load} is an instance method rather than a static
 * method because PHP does not support late static binding, at least until PHP
 * 5.3).
 *
 * To update an object, change its properties and save it:
 *
 *   $dog->setBreed('Lab')->save();
 *
 * To delete an object, call @{method:delete}:
 *
 *   $dog->delete();
 *
 * That's Lisk CRUD in a nutshell.
 *
 * = Queries =
 *
 * Often, you want to load a bunch of objects, or execute a more specialized
 * query. Use @{method:loadAllWhere} or @{method:loadOneWhere} to do this:
 *
 *   $pugs = $dog->loadAllWhere('breed = %s', 'Pug');
 *   $sawyer = $dog->loadOneWhere('name = %s', 'Sawyer');
 *
 * These methods work like @{function@libphutil:queryfx}, but only take half of
 * a query (the part after the WHERE keyword). Lisk will handle the connection,
 * columns, and object construction; you are responsible for the rest of it.
 * @{method:loadAllWhere} returns a list of objects, while
 * @{method:loadOneWhere} returns a single object (or `null`).
 *
 * There's also a @{method:loadRelatives} method which helps to prevent the 1+N
 * queries problem.
 *
 * = Managing Transactions =
 *
 * Lisk uses a transaction stack, so code does not generally need to be aware
 * of the transactional state of objects to implement correct transaction
 * semantics:
 *
 *   $obj->openTransaction();
 *     $obj->save();
 *     $other->save();
 *     // ...
 *     $other->openTransaction();
 *       $other->save();
 *       $another->save();
 *     if ($some_condition) {
 *       $other->saveTransaction();
 *     } else {
 *       $other->killTransaction();
 *     }
 *     // ...
 *   $obj->saveTransaction();
 *
 * Assuming ##$obj##, ##$other## and ##$another## live on the same database,
 * this code will work correctly by establishing savepoints.
 *
 * Selects whose data are used later in the transaction should be included in
 * @{method:beginReadLocking} or @{method:beginWriteLocking} block.
 *
 * @task   conn    Managing Connections
 * @task   config  Configuring Lisk
 * @task   load    Loading Objects
 * @task   info    Examining Objects
 * @task   save    Writing Objects
 * @task   hook    Hooks and Callbacks
 * @task   util    Utilities
 * @task   xaction Managing Transactions
 * @task   isolate Isolation for Unit Testing
 */
abstract class LiskDAO extends Phobject {

  const CONFIG_IDS                  = 'id-mechanism';
  const CONFIG_TIMESTAMPS           = 'timestamps';
  const CONFIG_AUX_PHID             = 'auxiliary-phid';
  const CONFIG_SERIALIZATION        = 'col-serialization';
  const CONFIG_BINARY               = 'binary';
  const CONFIG_COLUMN_SCHEMA        = 'col-schema';
  const CONFIG_KEY_SCHEMA           = 'key-schema';
  const CONFIG_NO_TABLE             = 'no-table';
  const CONFIG_NO_MUTATE            = 'no-mutate';

  const SERIALIZATION_NONE          = 'id';
  const SERIALIZATION_JSON          = 'json';
  const SERIALIZATION_PHP           = 'php';

  const IDS_AUTOINCREMENT           = 'ids-auto';
  const IDS_COUNTER                 = 'ids-counter';
  const IDS_MANUAL                  = 'ids-manual';

  const COUNTER_TABLE_NAME          = 'lisk_counter';

  private static $processIsolationLevel     = 0;
  private static $transactionIsolationLevel = 0;

  private $ephemeral = false;
  private $forcedConnection;

  private static $connections       = array();

  private $inSet = null;

  protected $id;
  protected $phid;
  protected $dateCreated;
  protected $dateModified;

  /**
   *  Build an empty object.
   *
   *  @return obj Empty object.
   */
  public function __construct() {
    $id_key = $this->getIDKey();
    if ($id_key) {
      $this->$id_key = null;
    }
  }


/* -(  Managing Connections  )----------------------------------------------- */


  /**
   * Establish a live connection to a database service. This method should
   * return a new connection. Lisk handles connection caching and management;
   * do not perform caching deeper in the stack.
   *
   * @param string Mode, either 'r' (reading) or 'w' (reading and writing).
   * @return AphrontDatabaseConnection New database connection.
   * @task conn
   */
  abstract protected function establishLiveConnection($mode);


  /**
   * Return a namespace for this object's connections in the connection cache.
   * Generally, the database name is appropriate. Two connections are considered
   * equivalent if they have the same connection namespace and mode.
   *
   * @return string Connection namespace for cache
   * @task conn
   */
  abstract protected function getConnectionNamespace();


  /**
   * Get an existing, cached connection for this object.
   *
   * @param mode Connection mode.
   * @return AprontDatabaseConnection|null  Connection, if it exists in cache.
   * @task conn
   */
  protected function getEstablishedConnection($mode) {
    $key = $this->getConnectionNamespace().':'.$mode;
    if (isset(self::$connections[$key])) {
      return self::$connections[$key];
    }
    return null;
  }


  /**
   * Store a connection in the connection cache.
   *
   * @param mode Connection mode.
   * @param AphrontDatabaseConnection Connection to cache.
   * @return this
   * @task conn
   */
  protected function setEstablishedConnection(
    $mode,
    AphrontDatabaseConnection $connection,
    $force_unique = false) {

    $key = $this->getConnectionNamespace().':'.$mode;

    if ($force_unique) {
      $key .= ':unique';
      while (isset(self::$connections[$key])) {
        $key .= '!';
      }
    }

    self::$connections[$key] = $connection;
    return $this;
  }


  /**
   * Force an object to use a specific connection.
   *
   * This overrides all connection management and forces the object to use
   * a specific connection when interacting with the database.
   *
   * @param AphrontDatabaseConnection Connection to force this object to use.
   * @task conn
   */
  public function setForcedConnection(AphrontDatabaseConnection $connection) {
    $this->forcedConnection = $connection;
    return $this;
  }


/* -(  Configuring Lisk  )--------------------------------------------------- */


  /**
   * Change Lisk behaviors, like ID configuration and timestamps. If you want
   * to change these behaviors, you should override this method in your child
   * class and change the options you're interested in. For example:
   *
   *   protected function getConfiguration() {
   *     return array(
   *       Lisk_DataAccessObject::CONFIG_EXAMPLE => true,
   *     ) + parent::getConfiguration();
   *   }
   *
   * The available options are:
   *
   * CONFIG_IDS
   * Lisk objects need to have a unique identifying ID. The three mechanisms
   * available for generating this ID are IDS_AUTOINCREMENT (default, assumes
   * the ID column is an autoincrement primary key), IDS_MANUAL (you are taking
   * full responsibility for ID management), or IDS_COUNTER (see below).
   *
   * InnoDB does not persist the value of `auto_increment` across restarts,
   * and instead initializes it to `MAX(id) + 1` during startup. This means it
   * may reissue the same autoincrement ID more than once, if the row is deleted
   * and then the database is restarted. To avoid this, you can set an object to
   * use a counter table with IDS_COUNTER. This will generally behave like
   * IDS_AUTOINCREMENT, except that the counter value will persist across
   * restarts and inserts will be slightly slower. If a database stores any
   * DAOs which use this mechanism, you must create a table there with this
   * schema:
   *
   *   CREATE TABLE lisk_counter (
   *     counterName VARCHAR(64) COLLATE utf8_bin PRIMARY KEY,
   *     counterValue BIGINT UNSIGNED NOT NULL
   *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
   *
   * CONFIG_TIMESTAMPS
   * Lisk can automatically handle keeping track of a `dateCreated' and
   * `dateModified' column, which it will update when it creates or modifies
   * an object. If you don't want to do this, you may disable this option.
   * By default, this option is ON.
   *
   * CONFIG_AUX_PHID
   * This option can be enabled by being set to some truthy value. The meaning
   * of this value is defined by your PHID generation mechanism. If this option
   * is enabled, a `phid' property will be populated with a unique PHID when an
   * object is created (or if it is saved and does not currently have one). You
   * need to override generatePHID() and hook it into your PHID generation
   * mechanism for this to work. By default, this option is OFF.
   *
   * CONFIG_SERIALIZATION
   * You can optionally provide a column serialization map that will be applied
   * to values when they are written to the database. For example:
   *
   *   self::CONFIG_SERIALIZATION => array(
   *     'complex' => self::SERIALIZATION_JSON,
   *   )
   *
   * This will cause Lisk to JSON-serialize the 'complex' field before it is
   * written, and unserialize it when it is read.
   *
   * CONFIG_BINARY
   * You can optionally provide a map of columns to a flag indicating that
   * they store binary data. These columns will not raise an error when
   * handling binary writes.
   *
   * CONFIG_COLUMN_SCHEMA
   * Provide a map of columns to schema column types.
   *
   * CONFIG_KEY_SCHEMA
   * Provide a map of key names to key specifications.
   *
   * CONFIG_NO_TABLE
   * Allows you to specify that this object does not actually have a table in
   * the database.
   *
   * CONFIG_NO_MUTATE
   * Provide a map of columns which should not be included in UPDATE statements.
   * If you have some columns which are always written to explicitly and should
   * never be overwritten by a save(), you can specify them here. This is an
   * advanced, specialized feature and there are usually better approaches for
   * most locking/contention problems.
   *
   * @return dictionary  Map of configuration options to values.
   *
   * @task   config
   */
  protected function getConfiguration() {
    return array(
      self::CONFIG_IDS                      => self::IDS_AUTOINCREMENT,
      self::CONFIG_TIMESTAMPS               => true,
    );
  }


  /**
   *  Determine the setting of a configuration option for this class of objects.
   *
   *  @param  const       Option name, one of the CONFIG_* constants.
   *  @return mixed       Option value, if configured (null if unavailable).
   *
   *  @task   config
   */
  public function getConfigOption($option_name) {
    static $options = null;

    if (!isset($options)) {
      $options = $this->getConfiguration();
    }

    return idx($options, $option_name);
  }


/* -(  Loading Objects  )---------------------------------------------------- */


  /**
   * Load an object by ID. You need to invoke this as an instance method, not
   * a class method, because PHP doesn't have late static binding (until
   * PHP 5.3.0). For example:
   *
   *   $dog = id(new Dog())->load($dog_id);
   *
   * @param  int       Numeric ID identifying the object to load.
   * @return obj|null  Identified object, or null if it does not exist.
   *
   * @task   load
   */
  public function load($id) {
    if (is_object($id)) {
      $id = (string)$id;
    }

    if (!$id || (!is_int($id) && !ctype_digit($id))) {
      return null;
    }

    return $this->loadOneWhere(
      '%C = %d',
      $this->getIDKeyForUse(),
      $id);
  }


  /**
   * Loads all of the objects, unconditionally.
   *
   * @return dict    Dictionary of all persisted objects of this type, keyed
   *                 on object ID.
   *
   * @task   load
   */
  public function loadAll() {
    return $this->loadAllWhere('1 = 1');
  }


  /**
   * Load all objects which match a WHERE clause. You provide everything after
   * the 'WHERE'; Lisk handles everything up to it. For example:
   *
   *   $old_dogs = id(new Dog())->loadAllWhere('age > %d', 7);
   *
   * The pattern and arguments are as per queryfx().
   *
   * @param  string  queryfx()-style SQL WHERE clause.
   * @param  ...     Zero or more conversions.
   * @return dict    Dictionary of matching objects, keyed on ID.
   *
   * @task   load
   */
  public function loadAllWhere($pattern /* , $arg, $arg, $arg ... */) {
    $args = func_get_args();
    $data = call_user_func_array(
      array($this, 'loadRawDataWhere'),
      $args);
    return $this->loadAllFromArray($data);
  }


  /**
   * Load a single object identified by a 'WHERE' clause. You provide
   * everything after the 'WHERE', and Lisk builds the first half of the
   * query. See loadAllWhere(). This method is similar, but returns a single
   * result instead of a list.
   *
   * @param  string    queryfx()-style SQL WHERE clause.
   * @param  ...       Zero or more conversions.
   * @return obj|null  Matching object, or null if no object matches.
   *
   * @task   load
   */
  public function loadOneWhere($pattern /* , $arg, $arg, $arg ... */) {
    $args = func_get_args();
    $data = call_user_func_array(
      array($this, 'loadRawDataWhere'),
      $args);

    if (count($data) > 1) {
      throw new AphrontCountQueryException(
        pht(
          'More than one result from %s!',
          __FUNCTION__.'()'));
    }

    $data = reset($data);
    if (!$data) {
      return null;
    }

    return $this->loadFromArray($data);
  }


  protected function loadRawDataWhere($pattern /* , $args... */) {
    $connection = $this->establishConnection('r');

    $lock_clause = '';
    if ($connection->isReadLocking()) {
      $lock_clause = 'FOR UPDATE';
    } else if ($connection->isWriteLocking()) {
      $lock_clause = 'LOCK IN SHARE MODE';
    }

    $args = func_get_args();
    $args = array_slice($args, 1);

    $pattern = 'SELECT * FROM %T WHERE '.$pattern.' %Q';
    array_unshift($args, $this->getTableName());
    array_push($args, $lock_clause);
    array_unshift($args, $pattern);

    return call_user_func_array(
      array($connection, 'queryData'),
      $args);
  }


  /**
   * Reload an object from the database, discarding any changes to persistent
   * properties. This is primarily useful after entering a transaction but
   * before applying changes to an object.
   *
   * @return this
   *
   * @task   load
   */
  public function reload() {
    if (!$this->getID()) {
      throw new Exception(
        pht("Unable to reload object that hasn't been loaded!"));
    }

    $result = $this->loadOneWhere(
      '%C = %d',
      $this->getIDKeyForUse(),
      $this->getID());

    if (!$result) {
      throw new AphrontObjectMissingQueryException();
    }

    return $this;
  }


  /**
   * Initialize this object's properties from a dictionary. Generally, you
   * load single objects with loadOneWhere(), but sometimes it may be more
   * convenient to pull data from elsewhere directly (e.g., a complicated
   * join via @{method:queryData}) and then load from an array representation.
   *
   * @param  dict  Dictionary of properties, which should be equivalent to
   *               selecting a row from the table or calling
   *               @{method:getProperties}.
   * @return this
   *
   * @task   load
   */
  public function loadFromArray(array $row) {
    static $valid_properties = array();

    $map = array();
    foreach ($row as $k => $v) {
      // We permit (but ignore) extra properties in the array because a
      // common approach to building the array is to issue a raw SELECT query
      // which may include extra explicit columns or joins.

      // This pathway is very hot on some pages, so we're inlining a cache
      // and doing some microoptimization to avoid a strtolower() call for each
      // assignment. The common path (assigning a valid property which we've
      // already seen) always incurs only one empty(). The second most common
      // path (assigning an invalid property which we've already seen) costs
      // an empty() plus an isset().

      if (empty($valid_properties[$k])) {
        if (isset($valid_properties[$k])) {
          // The value is set but empty, which means it's false, so we've
          // already determined it's not valid. We don't need to check again.
          continue;
        }
        $valid_properties[$k] = $this->hasProperty($k);
        if (!$valid_properties[$k]) {
          continue;
        }
      }

      $map[$k] = $v;
    }

    $this->willReadData($map);

    foreach ($map as $prop => $value) {
      $this->$prop = $value;
    }

    $this->didReadData();

    return $this;
  }


  /**
   * Initialize a list of objects from a list of dictionaries. Usually you
   * load lists of objects with @{method:loadAllWhere}, but sometimes that
   * isn't flexible enough. One case is if you need to do joins to select the
   * right objects:
   *
   *   function loadAllWithOwner($owner) {
   *     $data = $this->queryData(
   *       'SELECT d.*
   *         FROM owner o
   *           JOIN owner_has_dog od ON o.id = od.ownerID
   *           JOIN dog d ON od.dogID = d.id
   *         WHERE o.id = %d',
   *       $owner);
   *     return $this->loadAllFromArray($data);
   *   }
   *
   * This is a lot messier than @{method:loadAllWhere}, but more flexible.
   *
   * @param  list  List of property dictionaries.
   * @return dict  List of constructed objects, keyed on ID.
   *
   * @task   load
   */
  public function loadAllFromArray(array $rows) {
    $result = array();

    $id_key = $this->getIDKey();

    foreach ($rows as $row) {
      $obj = clone $this;
      if ($id_key && isset($row[$id_key])) {
        $result[$row[$id_key]] = $obj->loadFromArray($row);
      } else {
        $result[] = $obj->loadFromArray($row);
      }
      if ($this->inSet) {
        $this->inSet->addToSet($obj);
      }
    }

    return $result;
  }

  /**
   * This method helps to prevent the 1+N queries problem. It happens when you
   * execute a query for each row in a result set. Like in this code:
   *
   *   COUNTEREXAMPLE, name=Easy to write but expensive to execute
   *   $diffs = id(new DifferentialDiff())->loadAllWhere(
   *     'revisionID = %d',
   *     $revision->getID());
   *   foreach ($diffs as $diff) {
   *     $changesets = id(new DifferentialChangeset())->loadAllWhere(
   *       'diffID = %d',
   *       $diff->getID());
   *     // Do something with $changesets.
   *   }
   *
   * One can solve this problem by reading all the dependent objects at once and
   * assigning them later:
   *
   *   COUNTEREXAMPLE, name=Cheaper to execute but harder to write and maintain
   *   $diffs = id(new DifferentialDiff())->loadAllWhere(
   *     'revisionID = %d',
   *     $revision->getID());
   *   $all_changesets = id(new DifferentialChangeset())->loadAllWhere(
   *     'diffID IN (%Ld)',
   *     mpull($diffs, 'getID'));
   *   $all_changesets = mgroup($all_changesets, 'getDiffID');
   *   foreach ($diffs as $diff) {
   *     $changesets = idx($all_changesets, $diff->getID(), array());
   *     // Do something with $changesets.
   *   }
   *
   * The method @{method:loadRelatives} abstracts this approach which allows
   * writing a code which is simple and efficient at the same time:
   *
   *   name=Easy to write and cheap to execute
   *   $diffs = $revision->loadRelatives(new DifferentialDiff(), 'revisionID');
   *   foreach ($diffs as $diff) {
   *     $changesets = $diff->loadRelatives(
   *       new DifferentialChangeset(),
   *       'diffID');
   *     // Do something with $changesets.
   *   }
   *
   * This will load dependent objects for all diffs in the first call of
   * @{method:loadRelatives} and use this result for all following calls.
   *
   * The method supports working with set of sets, like in this code:
   *
   *   $diffs = $revision->loadRelatives(new DifferentialDiff(), 'revisionID');
   *   foreach ($diffs as $diff) {
   *     $changesets = $diff->loadRelatives(
   *       new DifferentialChangeset(),
   *       'diffID');
   *     foreach ($changesets as $changeset) {
   *       $hunks = $changeset->loadRelatives(
   *         new DifferentialHunk(),
   *         'changesetID');
   *       // Do something with hunks.
   *     }
   *   }
   *
   * This code will execute just three queries - one to load all diffs, one to
   * load all their related changesets and one to load all their related hunks.
   * You can try to write an equivalent code without using this method as
   * a homework.
   *
   * The method also supports retrieving referenced objects, for example authors
   * of all diffs (using shortcut @{method:loadOneRelative}):
   *
   *   foreach ($diffs as $diff) {
   *     $author = $diff->loadOneRelative(
   *       new PhabricatorUser(),
   *       'phid',
   *       'getAuthorPHID');
   *     // Do something with author.
   *   }
   *
   * It is also possible to specify additional conditions for the `WHERE`
   * clause. Similarly to @{method:loadAllWhere}, you can specify everything
   * after `WHERE` (except `LIMIT`). Contrary to @{method:loadAllWhere}, it is
   * allowed to pass only a constant string (`%` doesn't have a special
   * meaning). This is intentional to avoid mistakes with using data from one
   * row in retrieving other rows. Example of a correct usage:
   *
   *   $status = $author->loadOneRelative(
   *     new PhabricatorCalendarEvent(),
   *     'userPHID',
   *     'getPHID',
   *     '(UNIX_TIMESTAMP() BETWEEN dateFrom AND dateTo)');
   *
   * @param  LiskDAO  Type of objects to load.
   * @param  string   Name of the column in target table.
   * @param  string   Method name in this table.
   * @param  string   Additional constraints on returned rows. It supports no
   *                  placeholders and requires putting the WHERE part into
   *                  parentheses. It's not possible to use LIMIT.
   * @return list     Objects of type $object.
   *
   * @task   load
   */
  public function loadRelatives(
    LiskDAO $object,
    $foreign_column,
    $key_method = 'getID',
    $where = '') {

    if (!$this->inSet) {
      id(new LiskDAOSet())->addToSet($this);
    }
    $relatives = $this->inSet->loadRelatives(
      $object,
      $foreign_column,
      $key_method,
      $where);
    return idx($relatives, $this->$key_method(), array());
  }

  /**
   * Load referenced row. See @{method:loadRelatives} for details.
   *
   * @param  LiskDAO  Type of objects to load.
   * @param  string   Name of the column in target table.
   * @param  string   Method name in this table.
   * @param  string   Additional constraints on returned rows. It supports no
   *                  placeholders and requires putting the WHERE part into
   *                  parentheses. It's not possible to use LIMIT.
   * @return LiskDAO  Object of type $object or null if there's no such object.
   *
   * @task   load
   */
  final public function loadOneRelative(
    LiskDAO $object,
    $foreign_column,
    $key_method = 'getID',
    $where = '') {

    $relatives = $this->loadRelatives(
      $object,
      $foreign_column,
      $key_method,
      $where);

    if (!$relatives) {
      return null;
    }

    if (count($relatives) > 1) {
      throw new AphrontCountQueryException(
        pht(
          'More than one result from %s!',
          __FUNCTION__.'()'));
    }

    return reset($relatives);
  }

  final public function putInSet(LiskDAOSet $set) {
    $this->inSet = $set;
    return $this;
  }

  final protected function getInSet() {
    return $this->inSet;
  }


/* -(  Examining Objects  )-------------------------------------------------- */


  /**
   * Set unique ID identifying this object. You normally don't need to call this
   * method unless with `IDS_MANUAL`.
   *
   * @param  mixed   Unique ID.
   * @return this
   * @task   save
   */
  public function setID($id) {
    static $id_key = null;
    if ($id_key === null) {
      $id_key = $this->getIDKeyForUse();
    }
    $this->$id_key = $id;
    return $this;
  }


  /**
   * Retrieve the unique ID identifying this object. This value will be null if
   * the object hasn't been persisted and you didn't set it manually.
   *
   * @return mixed   Unique ID.
   *
   * @task   info
   */
  public function getID() {
    static $id_key = null;
    if ($id_key === null) {
      $id_key = $this->getIDKeyForUse();
    }
    return $this->$id_key;
  }


  public function getPHID() {
    return $this->phid;
  }


  /**
   * Test if a property exists.
   *
   * @param   string    Property name.
   * @return  bool      True if the property exists.
   * @task info
   */
  public function hasProperty($property) {
    return (bool)$this->checkProperty($property);
  }


  /**
   * Retrieve a list of all object properties. This list only includes
   * properties that are declared as protected, and it is expected that
   * all properties returned by this function should be persisted to the
   * database.
   * Properties that should not be persisted must be declared as private.
   *
   * @return dict  Dictionary of normalized (lowercase) to canonical (original
   *               case) property names.
   *
   * @task   info
   */
  protected function getAllLiskProperties() {
    static $properties = null;
    if (!isset($properties)) {
      $class = new ReflectionClass(get_class($this));
      $properties = array();
      foreach ($class->getProperties(ReflectionProperty::IS_PROTECTED) as $p) {
        $properties[strtolower($p->getName())] = $p->getName();
      }

      $id_key = $this->getIDKey();
      if ($id_key != 'id') {
        unset($properties['id']);
      }

      if (!$this->getConfigOption(self::CONFIG_TIMESTAMPS)) {
        unset($properties['datecreated']);
        unset($properties['datemodified']);
      }

      if ($id_key != 'phid' && !$this->getConfigOption(self::CONFIG_AUX_PHID)) {
        unset($properties['phid']);
      }
    }
    return $properties;
  }


  /**
   * Check if a property exists on this object.
   *
   * @return string|null   Canonical property name, or null if the property
   *                       does not exist.
   *
   * @task   info
   */
  protected function checkProperty($property) {
    static $properties = null;
    if ($properties === null) {
      $properties = $this->getAllLiskProperties();
    }

    $property = strtolower($property);
    if (empty($properties[$property])) {
      return null;
    }

    return $properties[$property];
  }


  /**
   * Get or build the database connection for this object.
   *
   * @param  string 'r' for read, 'w' for read/write.
   * @param  bool True to force a new connection. The connection will not
   *              be retrieved from or saved into the connection cache.
   * @return LiskDatabaseConnection   Lisk connection object.
   *
   * @task   info
   */
  public function establishConnection($mode, $force_new = false) {
    if ($mode != 'r' && $mode != 'w') {
      throw new Exception(
        pht(
          "Unknown mode '%s', should be 'r' or 'w'.",
          $mode));
    }

    if ($this->forcedConnection) {
      return $this->forcedConnection;
    }

    if (self::shouldIsolateAllLiskEffectsToCurrentProcess()) {
      $mode = 'isolate-'.$mode;

      $connection = $this->getEstablishedConnection($mode);
      if (!$connection) {
        $connection = $this->establishIsolatedConnection($mode);
        $this->setEstablishedConnection($mode, $connection);
      }

      return $connection;
    }

    if (self::shouldIsolateAllLiskEffectsToTransactions()) {
      // If we're doing fixture transaction isolation, force the mode to 'w'
      // so we always get the same connection for reads and writes, and thus
      // can see the writes inside the transaction.
      $mode = 'w';
    }

    // TODO: There is currently no protection on 'r' queries against writing.

    $connection = null;
    if (!$force_new) {
      if ($mode == 'r') {
        // If we're requesting a read connection but already have a write
        // connection, reuse the write connection so that reads can take place
        // inside transactions.
        $connection = $this->getEstablishedConnection('w');
      }

      if (!$connection) {
        $connection = $this->getEstablishedConnection($mode);
      }
    }

    if (!$connection) {
      $connection = $this->establishLiveConnection($mode);
      if (self::shouldIsolateAllLiskEffectsToTransactions()) {
        $connection->openTransaction();
      }
      $this->setEstablishedConnection(
        $mode,
        $connection,
        $force_unique = $force_new);
    }

    return $connection;
  }


  /**
   * Convert this object into a property dictionary. This dictionary can be
   * restored into an object by using @{method:loadFromArray} (unless you're
   * using legacy features with CONFIG_CONVERT_CAMELCASE, but in that case you
   * should just go ahead and die in a fire).
   *
   * @return dict  Dictionary of object properties.
   *
   * @task   info
   */
  protected function getAllLiskPropertyValues() {
    $map = array();
    foreach ($this->getAllLiskProperties() as $p) {
      // We may receive a warning here for properties we've implicitly added
      // through configuration; squelch it.
      $map[$p] = @$this->$p;
    }
    return $map;
  }


/* -(  Writing Objects  )---------------------------------------------------- */


  /**
   * Make an object read-only.
   *
   * Making an object ephemeral indicates that you will be changing state in
   * such a way that you would never ever want it to be written back to the
   * storage.
   */
  public function makeEphemeral() {
    $this->ephemeral = true;
    return $this;
  }

  private function isEphemeralCheck() {
    if ($this->ephemeral) {
      throw new LiskEphemeralObjectException();
    }
  }

  /**
   * Persist this object to the database. In most cases, this is the only
   * method you need to call to do writes. If the object has not yet been
   * inserted this will do an insert; if it has, it will do an update.
   *
   * @return this
   *
   * @task   save
   */
  public function save() {
    if ($this->shouldInsertWhenSaved()) {
      return $this->insert();
    } else {
      return $this->update();
    }
  }


  /**
   * Save this object, forcing the query to use REPLACE regardless of object
   * state.
   *
   * @return this
   *
   * @task   save
   */
  public function replace() {
    $this->isEphemeralCheck();
    return $this->insertRecordIntoDatabase('REPLACE');
  }


  /**
   *  Save this object, forcing the query to use INSERT regardless of object
   *  state.
   *
   *  @return this
   *
   *  @task   save
   */
  public function insert() {
    $this->isEphemeralCheck();
    return $this->insertRecordIntoDatabase('INSERT');
  }


  /**
   *  Save this object, forcing the query to use UPDATE regardless of object
   *  state.
   *
   *  @return this
   *
   *  @task   save
   */
  public function update() {
    $this->isEphemeralCheck();

    $this->willSaveObject();
    $data = $this->getAllLiskPropertyValues();

    // Remove colums flagged as nonmutable from the update statement.
    $no_mutate = $this->getConfigOption(self::CONFIG_NO_MUTATE);
    if ($no_mutate) {
      foreach ($no_mutate as $column) {
        unset($data[$column]);
      }
    }

    $this->willWriteData($data);

    $map = array();
    foreach ($data as $k => $v) {
      $map[$k] = $v;
    }

    $conn = $this->establishConnection('w');
    $binary = $this->getBinaryColumns();

    foreach ($map as $key => $value) {
      if (!empty($binary[$key])) {
        $map[$key] = qsprintf($conn, '%C = %nB', $key, $value);
      } else {
        $map[$key] = qsprintf($conn, '%C = %ns', $key, $value);
      }
    }
    $map = implode(', ', $map);

    $id = $this->getID();
    $conn->query(
      'UPDATE %T SET %Q WHERE %C = '.(is_int($id) ? '%d' : '%s'),
      $this->getTableName(),
      $map,
      $this->getIDKeyForUse(),
      $id);
    // We can't detect a missing object because updating an object without
    // changing any values doesn't affect rows. We could jiggle timestamps
    // to catch this for objects which track them if we wanted.

    $this->didWriteData();

    return $this;
  }


  /**
   * Delete this object, permanently.
   *
   * @return this
   *
   * @task   save
   */
  public function delete() {
    $this->isEphemeralCheck();
    $this->willDelete();

    $conn = $this->establishConnection('w');
    $conn->query(
      'DELETE FROM %T WHERE %C = %d',
      $this->getTableName(),
      $this->getIDKeyForUse(),
      $this->getID());

    $this->didDelete();

    return $this;
  }

  /**
   * Internal implementation of INSERT and REPLACE.
   *
   * @param  const   Either "INSERT" or "REPLACE", to force the desired mode.
   *
   * @task   save
   */
  protected function insertRecordIntoDatabase($mode) {
    $this->willSaveObject();
    $data = $this->getAllLiskPropertyValues();

    $conn = $this->establishConnection('w');

    $id_mechanism = $this->getConfigOption(self::CONFIG_IDS);
    switch ($id_mechanism) {
      case self::IDS_AUTOINCREMENT:
        // If we are using autoincrement IDs, let MySQL assign the value for the
        // ID column, if it is empty. If the caller has explicitly provided a
        // value, use it.
        $id_key = $this->getIDKeyForUse();
        if (empty($data[$id_key])) {
          unset($data[$id_key]);
        }
        break;
      case self::IDS_COUNTER:
        // If we are using counter IDs, assign a new ID if we don't already have
        // one.
        $id_key = $this->getIDKeyForUse();
        if (empty($data[$id_key])) {
          $counter_name = $this->getTableName();
          $id = self::loadNextCounterValue($conn, $counter_name);
          $this->setID($id);
          $data[$id_key] = $id;
        }
        break;
      case self::IDS_MANUAL:
        break;
      default:
        throw new Exception(pht('Unknown %s mechanism!', 'CONFIG_IDs'));
    }

    $this->willWriteData($data);

    $columns = array_keys($data);
    $binary = $this->getBinaryColumns();

    foreach ($data as $key => $value) {
      try {
        if (!empty($binary[$key])) {
          $data[$key] = qsprintf($conn, '%nB', $value);
        } else {
          $data[$key] = qsprintf($conn, '%ns', $value);
        }
      } catch (AphrontParameterQueryException $parameter_exception) {
        throw new PhutilProxyException(
          pht(
            "Unable to insert or update object of class %s, field '%s' ".
            "has a non-scalar value.",
            get_class($this),
            $key),
          $parameter_exception);
      }
    }
    $data = implode(', ', $data);

    $conn->query(
      '%Q INTO %T (%LC) VALUES (%Q)',
      $mode,
      $this->getTableName(),
      $columns,
      $data);

    // Only use the insert id if this table is using auto-increment ids
    if ($id_mechanism === self::IDS_AUTOINCREMENT) {
      $this->setID($conn->getInsertID());
    }

    $this->didWriteData();

    return $this;
  }


  /**
   * Method used to determine whether to insert or update when saving.
   *
   * @return bool true if the record should be inserted
   */
  protected function shouldInsertWhenSaved() {
    $key_type = $this->getConfigOption(self::CONFIG_IDS);

    if ($key_type == self::IDS_MANUAL) {
      throw new Exception(
        pht(
          'You are using manual IDs. You must override the %s method '.
          'to properly detect when to insert a new record.',
          __FUNCTION__.'()'));
    } else {
      return !$this->getID();
    }
  }


/* -(  Hooks and Callbacks  )------------------------------------------------ */


  /**
   * Retrieve the database table name. By default, this is the class name.
   *
   * @return string  Table name for object storage.
   *
   * @task   hook
   */
  public function getTableName() {
    return get_class($this);
  }


  /**
   * Retrieve the primary key column, "id" by default. If you can not
   * reasonably name your ID column "id", override this method.
   *
   * @return string  Name of the ID column.
   *
   * @task   hook
   */
  public function getIDKey() {
    return 'id';
  }


  protected function getIDKeyForUse() {
    $id_key = $this->getIDKey();
    if (!$id_key) {
      throw new Exception(
        pht(
          'This DAO does not have a single-part primary key. The method you '.
          'called requires a single-part primary key.'));
    }
    return $id_key;
  }


  /**
   * Generate a new PHID, used by CONFIG_AUX_PHID.
   *
   * @return phid    Unique, newly allocated PHID.
   *
   * @task   hook
   */
  public function generatePHID() {
    throw new Exception(
      pht(
        'To use %s, you need to overload %s to perform PHID generation.',
        'CONFIG_AUX_PHID',
        'generatePHID()'));
  }


  /**
   * Hook to apply serialization or validation to data before it is written to
   * the database. See also @{method:willReadData}.
   *
   * @task hook
   */
  protected function willWriteData(array &$data) {
    $this->applyLiskDataSerialization($data, false);
  }


  /**
   * Hook to perform actions after data has been written to the database.
   *
   * @task hook
   */
  protected function didWriteData() {}


  /**
   * Hook to make internal object state changes prior to INSERT, REPLACE or
   * UPDATE.
   *
   * @task hook
   */
  protected function willSaveObject() {
    $use_timestamps = $this->getConfigOption(self::CONFIG_TIMESTAMPS);

    if ($use_timestamps) {
      if (!$this->getDateCreated()) {
        $this->setDateCreated(time());
      }
      $this->setDateModified(time());
    }

    if ($this->getConfigOption(self::CONFIG_AUX_PHID) && !$this->getPHID()) {
      $this->setPHID($this->generatePHID());
    }
  }


  /**
   * Hook to apply serialization or validation to data as it is read from the
   * database. See also @{method:willWriteData}.
   *
   * @task hook
   */
  protected function willReadData(array &$data) {
    $this->applyLiskDataSerialization($data, $deserialize = true);
  }

  /**
   * Hook to perform an action on data after it is read from the database.
   *
   * @task hook
   */
  protected function didReadData() {}

  /**
   * Hook to perform an action before the deletion of an object.
   *
   * @task hook
   */
  protected function willDelete() {}

  /**
   * Hook to perform an action after the deletion of an object.
   *
   * @task hook
   */
  protected function didDelete() {}

  /**
   * Reads the value from a field. Override this method for custom behavior
   * of @{method:getField} instead of overriding getField directly.
   *
   * @param  string  Canonical field name
   * @return mixed   Value of the field
   *
   * @task hook
   */
  protected function readField($field) {
    if (isset($this->$field)) {
      return $this->$field;
    }
    return null;
  }

  /**
   * Writes a value to a field. Override this method for custom behavior of
   * setField($value) instead of overriding setField directly.
   *
   * @param  string  Canonical field name
   * @param  mixed   Value to write
   *
   * @task hook
   */
  protected function writeField($field, $value) {
    $this->$field = $value;
  }


/* -(  Manging Transactions  )----------------------------------------------- */


  /**
   * Increase transaction stack depth.
   *
   * @return this
   */
  public function openTransaction() {
    $this->establishConnection('w')->openTransaction();
    return $this;
  }


  /**
   * Decrease transaction stack depth, saving work.
   *
   * @return this
   */
  public function saveTransaction() {
    $this->establishConnection('w')->saveTransaction();
    return $this;
  }


  /**
   * Decrease transaction stack depth, discarding work.
   *
   * @return this
   */
  public function killTransaction() {
    $this->establishConnection('w')->killTransaction();
    return $this;
  }


  /**
   * Begins read-locking selected rows with SELECT ... FOR UPDATE, so that
   * other connections can not read them (this is an enormous oversimplification
   * of FOR UPDATE semantics; consult the MySQL documentation for details). To
   * end read locking, call @{method:endReadLocking}. For example:
   *
   *   $beach->openTransaction();
   *     $beach->beginReadLocking();
   *
   *       $beach->reload();
   *       $beach->setGrainsOfSand($beach->getGrainsOfSand() + 1);
   *       $beach->save();
   *
   *     $beach->endReadLocking();
   *   $beach->saveTransaction();
   *
   * @return this
   * @task xaction
   */
  public function beginReadLocking() {
    $this->establishConnection('w')->beginReadLocking();
    return $this;
  }


  /**
   * Ends read-locking that began at an earlier @{method:beginReadLocking} call.
   *
   * @return this
   * @task xaction
   */
  public function endReadLocking() {
    $this->establishConnection('w')->endReadLocking();
    return $this;
  }

  /**
   * Begins write-locking selected rows with SELECT ... LOCK IN SHARE MODE, so
   * that other connections can not update or delete them (this is an
   * oversimplification of LOCK IN SHARE MODE semantics; consult the
   * MySQL documentation for details). To end write locking, call
   * @{method:endWriteLocking}.
   *
   * @return this
   * @task xaction
   */
  public function beginWriteLocking() {
    $this->establishConnection('w')->beginWriteLocking();
    return $this;
  }


  /**
   * Ends write-locking that began at an earlier @{method:beginWriteLocking}
   * call.
   *
   * @return this
   * @task xaction
   */
  public function endWriteLocking() {
    $this->establishConnection('w')->endWriteLocking();
    return $this;
  }


/* -(  Isolation  )---------------------------------------------------------- */


  /**
   * @task isolate
   */
  public static function beginIsolateAllLiskEffectsToCurrentProcess() {
    self::$processIsolationLevel++;
  }

  /**
   * @task isolate
   */
  public static function endIsolateAllLiskEffectsToCurrentProcess() {
    self::$processIsolationLevel--;
    if (self::$processIsolationLevel < 0) {
      throw new Exception(
        pht('Lisk process isolation level was reduced below 0.'));
    }
  }

  /**
   * @task isolate
   */
  public static function shouldIsolateAllLiskEffectsToCurrentProcess() {
    return (bool)self::$processIsolationLevel;
  }

  /**
   * @task isolate
   */
  private function establishIsolatedConnection($mode) {
    $config = array();
    return new AphrontIsolatedDatabaseConnection($config);
  }

  /**
   * @task isolate
   */
  public static function beginIsolateAllLiskEffectsToTransactions() {
    if (self::$transactionIsolationLevel === 0) {
      self::closeAllConnections();
    }
    self::$transactionIsolationLevel++;
  }

  /**
   * @task isolate
   */
  public static function endIsolateAllLiskEffectsToTransactions() {
    self::$transactionIsolationLevel--;
    if (self::$transactionIsolationLevel < 0) {
      throw new Exception(
        pht('Lisk transaction isolation level was reduced below 0.'));
    } else if (self::$transactionIsolationLevel == 0) {
      foreach (self::$connections as $key => $conn) {
        if ($conn) {
          $conn->killTransaction();
        }
      }
      self::closeAllConnections();
    }
  }

  /**
   * @task isolate
   */
  public static function shouldIsolateAllLiskEffectsToTransactions() {
    return (bool)self::$transactionIsolationLevel;
  }

  public static function closeAllConnections() {
    self::$connections = array();
  }

/* -(  Utilities  )---------------------------------------------------------- */


  /**
   * Applies configured serialization to a dictionary of values.
   *
   * @task util
   */
  protected function applyLiskDataSerialization(array &$data, $deserialize) {
    $serialization = $this->getConfigOption(self::CONFIG_SERIALIZATION);
    if ($serialization) {
      foreach (array_intersect_key($serialization, $data) as $col => $format) {
        switch ($format) {
          case self::SERIALIZATION_NONE:
            break;
          case self::SERIALIZATION_PHP:
            if ($deserialize) {
              $data[$col] = unserialize($data[$col]);
            } else {
              $data[$col] = serialize($data[$col]);
            }
            break;
          case self::SERIALIZATION_JSON:
            if ($deserialize) {
              $data[$col] = json_decode($data[$col], true);
            } else {
              $data[$col] = phutil_json_encode($data[$col]);
            }
            break;
          default:
            throw new Exception(
              pht("Unknown serialization format '%s'.", $format));
        }
      }
    }
  }

  /**
   * Black magic. Builds implied get*() and set*() for all properties.
   *
   * @param  string  Method name.
   * @param  list    Argument vector.
   * @return mixed   get*() methods return the property value. set*() methods
   *                 return $this.
   * @task   util
   */
  public function __call($method, $args) {
    // NOTE: PHP has a bug that static variables defined in __call() are shared
    // across all children classes. Call a different method to work around this
    // bug.
    return $this->call($method, $args);
  }

  /**
   * @task   util
   */
  final protected function call($method, $args) {
    // NOTE: This method is very performance-sensitive (many thousands of calls
    // per page on some pages), and thus has some silliness in the name of
    // optimizations.

    static $dispatch_map = array();

    if ($method[0] === 'g') {
      if (isset($dispatch_map[$method])) {
        $property = $dispatch_map[$method];
      } else {
        if (substr($method, 0, 3) !== 'get') {
          throw new Exception(pht("Unable to resolve method '%s'!", $method));
        }
        $property = substr($method, 3);
        if (!($property = $this->checkProperty($property))) {
          throw new Exception(pht('Bad getter call: %s', $method));
        }
        $dispatch_map[$method] = $property;
      }

      return $this->readField($property);
    }

    if ($method[0] === 's') {
      if (isset($dispatch_map[$method])) {
        $property = $dispatch_map[$method];
      } else {
        if (substr($method, 0, 3) !== 'set') {
          throw new Exception(pht("Unable to resolve method '%s'!", $method));
        }
        $property = substr($method, 3);
        $property = $this->checkProperty($property);
        if (!$property) {
          throw new Exception(pht('Bad setter call: %s', $method));
        }
        $dispatch_map[$method] = $property;
      }

      $this->writeField($property, $args[0]);

      return $this;
    }

    throw new Exception(pht("Unable to resolve method '%s'.", $method));
  }

  /**
   * Warns against writing to undeclared property.
   *
   * @task   util
   */
  public function __set($name, $value) {
    phlog(
      pht(
        'Wrote to undeclared property %s.',
        get_class($this).'::$'.$name));
    $this->$name = $value;
  }


  /**
   * Increments a named counter and returns the next value.
   *
   * @param   AphrontDatabaseConnection   Database where the counter resides.
   * @param   string                      Counter name to create or increment.
   * @return  int                         Next counter value.
   *
   * @task util
   */
  public static function loadNextCounterValue(
    AphrontDatabaseConnection $conn_w,
    $counter_name) {

    // NOTE: If an insert does not touch an autoincrement row or call
    // LAST_INSERT_ID(), MySQL normally does not change the value of
    // LAST_INSERT_ID(). This can cause a counter's value to leak to a
    // new counter if the second counter is created after the first one is
    // updated. To avoid this, we insert LAST_INSERT_ID(1), to ensure the
    // LAST_INSERT_ID() is always updated and always set correctly after the
    // query completes.

    queryfx(
      $conn_w,
      'INSERT INTO %T (counterName, counterValue) VALUES
          (%s, LAST_INSERT_ID(1))
        ON DUPLICATE KEY UPDATE
          counterValue = LAST_INSERT_ID(counterValue + 1)',
      self::COUNTER_TABLE_NAME,
      $counter_name);

    return $conn_w->getInsertID();
  }


  /**
   * Returns the current value of a named counter.
   *
   * @param AphrontDatabaseConnection Database where the counter resides.
   * @param string Counter name to read.
   * @return int|null Current value, or `null` if the counter does not exist.
   *
   * @task util
   */
  public static function loadCurrentCounterValue(
    AphrontDatabaseConnection $conn_r,
    $counter_name) {

    $row = queryfx_one(
      $conn_r,
      'SELECT counterValue FROM %T WHERE counterName = %s',
      self::COUNTER_TABLE_NAME,
      $counter_name);
    if (!$row) {
      return null;
    }

    return (int)$row['counterValue'];
  }


  /**
   * Overwrite a named counter, forcing it to a specific value.
   *
   * If the counter does not exist, it is created.
   *
   * @param AphrontDatabaseConnection Database where the counter resides.
   * @param string Counter name to create or overwrite.
   * @return void
   *
   * @task util
   */
  public static function overwriteCounterValue(
    AphrontDatabaseConnection $conn_w,
    $counter_name,
    $counter_value) {

    queryfx(
      $conn_w,
      'INSERT INTO %T (counterName, counterValue) VALUES (%s, %d)
        ON DUPLICATE KEY UPDATE counterValue = VALUES(counterValue)',
      self::COUNTER_TABLE_NAME,
      $counter_name,
      $counter_value);
  }

  private function getBinaryColumns() {
    return $this->getConfigOption(self::CONFIG_BINARY);
  }


  public function getSchemaColumns() {
    $custom_map = $this->getConfigOption(self::CONFIG_COLUMN_SCHEMA);
    if (!$custom_map) {
      $custom_map = array();
    }

    $serialization = $this->getConfigOption(self::CONFIG_SERIALIZATION);
    if (!$serialization) {
      $serialization = array();
    }

    $serialization_map = array(
      self::SERIALIZATION_JSON => 'text',
      self::SERIALIZATION_PHP => 'bytes',
    );

    $binary_map = $this->getBinaryColumns();

    $id_mechanism = $this->getConfigOption(self::CONFIG_IDS);
    if ($id_mechanism == self::IDS_AUTOINCREMENT) {
      $id_type = 'auto';
    } else {
      $id_type = 'id';
    }

    $builtin = array(
      'id' => $id_type,
      'phid' => 'phid',
      'viewPolicy' => 'policy',
      'editPolicy' => 'policy',
      'epoch' => 'epoch',
      'dateCreated' => 'epoch',
      'dateModified' => 'epoch',
    );

    $map = array();
    foreach ($this->getAllLiskProperties() as $property) {
      // First, use types specified explicitly in the table configuration.
      if (array_key_exists($property, $custom_map)) {
        $map[$property] = $custom_map[$property];
        continue;
      }

      // If we don't have an explicit type, try a builtin type for the
      // column.
      $type = idx($builtin, $property);
      if ($type) {
        $map[$property] = $type;
        continue;
      }

      // If the column has serialization, we can infer the column type.
      if (isset($serialization[$property])) {
        $type = idx($serialization_map, $serialization[$property]);
        if ($type) {
          $map[$property] = $type;
          continue;
        }
      }

      if (isset($binary_map[$property])) {
        $map[$property] = 'bytes';
        continue;
      }

      if ($property === 'spacePHID') {
        $map[$property] = 'phid?';
        continue;
      }

      // If the column is named `somethingPHID`, infer it is a PHID.
      if (preg_match('/[a-z]PHID$/', $property)) {
        $map[$property] = 'phid';
        continue;
      }

      // If the column is named `somethingID`, infer it is an ID.
      if (preg_match('/[a-z]ID$/', $property)) {
        $map[$property] = 'id';
        continue;
      }

      // We don't know the type of this column.
      $map[$property] = PhabricatorConfigSchemaSpec::DATATYPE_UNKNOWN;
    }

    return $map;
  }

  public function getSchemaKeys() {
    $custom_map = $this->getConfigOption(self::CONFIG_KEY_SCHEMA);
    if (!$custom_map) {
      $custom_map = array();
    }

    $default_map = array();
    foreach ($this->getAllLiskProperties() as $property) {
      switch ($property) {
        case 'id':
          $default_map['PRIMARY'] = array(
            'columns' => array('id'),
            'unique' => true,
          );
          break;
        case 'phid':
          $default_map['key_phid'] = array(
            'columns' => array('phid'),
            'unique' => true,
          );
          break;
        case 'spacePHID':
          $default_map['key_space'] = array(
            'columns' => array('spacePHID'),
          );
          break;
      }
    }

    return $custom_map + $default_map;
  }

}
