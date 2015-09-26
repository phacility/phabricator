ALTER TABLE {$NAMESPACE}_drydock.drydock_lease
  CHANGE status status VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};

UPDATE {$NAMESPACE}_drydock.drydock_lease
  SET status = 'pending' WHERE status = '0';

UPDATE {$NAMESPACE}_drydock.drydock_lease
  SET status = 'acquired' WHERE status = '5';

UPDATE {$NAMESPACE}_drydock.drydock_lease
  SET status = 'active' WHERE status = '1';

UPDATE {$NAMESPACE}_drydock.drydock_lease
  SET status = 'released' WHERE status = '2';

UPDATE {$NAMESPACE}_drydock.drydock_lease
  SET status = 'broken' WHERE status = '3';

UPDATE {$NAMESPACE}_drydock.drydock_lease
  SET status = 'destroyed' WHERE status = '4';


ALTER TABLE {$NAMESPACE}_drydock.drydock_resource
  CHANGE status status VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};

UPDATE {$NAMESPACE}_drydock.drydock_resource
  SET status = 'pending' WHERE status = '0';

UPDATE {$NAMESPACE}_drydock.drydock_resource
  SET status = 'active' WHERE status = '1';

UPDATE {$NAMESPACE}_drydock.drydock_resource
  SET status = 'released' WHERE status = '2';

UPDATE {$NAMESPACE}_drydock.drydock_resource
  SET status = 'broken' WHERE status = '3';

UPDATE {$NAMESPACE}_drydock.drydock_resource
  SET status = 'destroyed' WHERE status = '4';
