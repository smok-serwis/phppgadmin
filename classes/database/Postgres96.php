<?php

/**
 * A Class that implements the DB Interface for Postgres
 * Note: This Class uses ADODB and returns RecordSets.
 *
 * $Id: Postgres.php,v 1.320 2008/02/20 20:43:09 ioguix Exp $
 */

include_once('./classes/database/Postgres10.php');

class Postgres96 extends Postgres10 {
	var $major_version = 9.6;

	/**
	 * Constructor
	 * @param $conn The database connection
	 */
	function __construct($conn) {
		parent::__construct($conn);
	}


	// Sequence functions
	// Sequences had some changes in PostgreSQL 10 so just duplicating the old ones here.

	/**
	 * Returns properties of a single sequence
	 * @param $sequence Sequence name
	 * @return A recordset
	 */
	function getSequence($sequence) {
		$c_schema = $this->_schema;
		$this->clean($c_schema);
		$c_sequence = $sequence;
		$this->fieldClean($sequence);
		$this->clean($c_sequence);

		$sql = "
			SELECT c.relname AS seqname, s.*,
				pg_catalog.obj_description(s.tableoid, 'pg_class') AS seqcomment,
				u.usename AS seqowner, n.nspname
			FROM \"{$sequence}\" AS s, pg_catalog.pg_class c, pg_catalog.pg_user u, pg_catalog.pg_namespace n
			WHERE c.relowner=u.usesysid AND c.relnamespace=n.oid
				AND c.relname = '{$c_sequence}' AND c.relkind = 'S' AND n.nspname='{$c_schema}'
				AND n.oid = c.relnamespace";

		return $this->selectSet( $sql );
	}

	/**
	 * Returns all sequences in the current database
	 * @return A recordset
	 */
	function getSequences($all = false) {
		if ($all) {
			// Exclude pg_catalog and information_schema tables
			$sql = "SELECT n.nspname, c.relname AS seqname, u.usename AS seqowner
				FROM pg_catalog.pg_class c, pg_catalog.pg_user u, pg_catalog.pg_namespace n
				WHERE c.relowner=u.usesysid AND c.relnamespace=n.oid
				AND c.relkind = 'S'
				AND n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
				ORDER BY nspname, seqname";
		} else {
			$c_schema = $this->_schema;
			$this->clean($c_schema);
			$sql = "SELECT c.relname AS seqname, u.usename AS seqowner, pg_catalog.obj_description(c.oid, 'pg_class') AS seqcomment,
				(SELECT spcname FROM pg_catalog.pg_tablespace pt WHERE pt.oid=c.reltablespace) AS tablespace
				FROM pg_catalog.pg_class c, pg_catalog.pg_user u, pg_catalog.pg_namespace n
				WHERE c.relowner=u.usesysid AND c.relnamespace=n.oid
				AND c.relkind = 'S' AND n.nspname='{$c_schema}' ORDER BY seqname";
		}

		return $this->selectSet( $sql );
	}

	/**
	 * Execute nextval on a given sequence
	 * @param $sequence Sequence name
	 * @return 0 success
	 * @return -1 sequence not found
	 */
	function nextvalSequence($sequence) {
		/* This double-cleaning is deliberate */
		$f_schema = $this->_schema;
		$this->fieldClean($f_schema);
		$this->clean($f_schema);
		$this->fieldClean($sequence);
		$this->clean($sequence);

		$sql = "SELECT pg_catalog.NEXTVAL('\"{$f_schema}\".\"{$sequence}\"')";

		return $this->execute($sql);
	}

	/**
	 * Execute setval on a given sequence
	 * @param $sequence Sequence name
	 * @param $nextvalue The next value
	 * @return 0 success
	 * @return -1 sequence not found
	 */
	function setvalSequence($sequence, $nextvalue) {
		/* This double-cleaning is deliberate */
		$f_schema = $this->_schema;
		$this->fieldClean($f_schema);
		$this->clean($f_schema);
		$this->fieldClean($sequence);
		$this->clean($sequence);
		$this->clean($nextvalue);

		$sql = "SELECT pg_catalog.SETVAL('\"{$f_schema}\".\"{$sequence}\"', '{$nextvalue}')";

		return $this->execute($sql);
	}

	/**
	 * Restart a given sequence to its start value
	 * @param $sequence Sequence name
	 * @return 0 success
	 * @return -1 sequence not found
	 */
	function restartSequence($sequence) {

		$f_schema = $this->_schema;
		$this->fieldClean($f_schema);
		$this->fieldClean($sequence);

		$sql = "ALTER SEQUENCE \"{$f_schema}\".\"{$sequence}\" RESTART;";

		return $this->execute($sql);
	}

	/**
	 * Resets a given sequence to min value of sequence
	 * @param $sequence Sequence name
	 * @return 0 success
	 * @return -1 sequence not found
	 */
	function resetSequence($sequence) {
		// Get the minimum value of the sequence
		$seq = $this->getSequence($sequence);
		if ($seq->recordCount() != 1) return -1;
		$minvalue = $seq->fields['min_value'];

		$f_schema = $this->_schema;
		$this->fieldClean($f_schema);
		/* This double-cleaning is deliberate */
		$this->fieldClean($sequence);
		$this->clean($sequence);

		$sql = "SELECT pg_catalog.SETVAL('\"{$f_schema}\".\"{$sequence}\"', {$minvalue})";

		return $this->execute($sql);
	}

	/**
	 * Creates a new sequence
	 * @param $sequence Sequence name
	 * @param $increment The increment
	 * @param $minvalue The min value
	 * @param $maxvalue The max value
	 * @param $startvalue The starting value
	 * @param $cachevalue The cache value
	 * @param $cycledvalue True if cycled, false otherwise
	 * @return 0 success
	 */
	function createSequence($sequence, $increment, $minvalue, $maxvalue,
								$startvalue, $cachevalue, $cycledvalue) {
		$f_schema = $this->_schema;
		$this->fieldClean($f_schema);
		$this->fieldClean($sequence);
		$this->clean($increment);
		$this->clean($minvalue);
		$this->clean($maxvalue);
		$this->clean($startvalue);
		$this->clean($cachevalue);

		$sql = "CREATE SEQUENCE \"{$f_schema}\".\"{$sequence}\"";
		if ($increment != '') $sql .= " INCREMENT {$increment}";
		if ($minvalue != '') $sql .= " MINVALUE {$minvalue}";
		if ($maxvalue != '') $sql .= " MAXVALUE {$maxvalue}";
		if ($startvalue != '') $sql .= " START {$startvalue}";
		if ($cachevalue != '') $sql .= " CACHE {$cachevalue}";
		if ($cycledvalue) $sql .= " CYCLE";

		return $this->execute($sql);
	}

	/**
	 * Rename a sequence
	 * @param $seqrs The sequence RecordSet returned by getSequence()
	 * @param $name The new name for the sequence
	 * @return 0 success
	 */
	function alterSequenceName($seqrs, $name) {
		/* vars are cleaned in _alterSequence */
		if (!empty($name) && ($seqrs->fields['seqname'] != $name)) {
			$f_schema = $this->_schema;
			$this->fieldClean($f_schema);
			$sql = "ALTER SEQUENCE \"{$f_schema}\".\"{$seqrs->fields['seqname']}\" RENAME TO \"{$name}\"";
			$status = $this->execute($sql);
			if ($status == 0)
				$seqrs->fields['seqname'] = $name;
			else
				return $status;
		}
		return 0;
	}

	/**
	 * Alter a sequence's owner
	 * @param $seqrs The sequence RecordSet returned by getSequence()
	 * @param $name The new owner for the sequence
	 * @return 0 success
	 */
	function alterSequenceOwner($seqrs, $owner) {
		// If owner has been changed, then do the alteration.  We are
		// careful to avoid this generally as changing owner is a
		// superuser only function.
		/* vars are cleaned in _alterSequence */
		if (!empty($owner) && ($seqrs->fields['seqowner'] != $owner)) {
			$f_schema = $this->_schema;
			$this->fieldClean($f_schema);
			$sql = "ALTER SEQUENCE \"{$f_schema}\".\"{$seqrs->fields['seqname']}\" OWNER TO \"{$owner}\"";
			return $this->execute($sql);
		}
		return 0;
	}

	/**
	 * Alter a sequence's schema
	 * @param $seqrs The sequence RecordSet returned by getSequence()
	 * @param $name The new schema for the sequence
	 * @return 0 success
	 */
	function alterSequenceSchema($seqrs, $schema) {
		/* vars are cleaned in _alterSequence */
		if (!empty($schema) && ($seqrs->fields['nspname'] != $schema)) {
			$f_schema = $this->_schema;
			$this->fieldClean($f_schema);
			$sql = "ALTER SEQUENCE \"{$f_schema}\".\"{$seqrs->fields['seqname']}\" SET SCHEMA {$schema}";
			return $this->execute($sql);
		}
		return 0;
	}

	/**
	 * Alter a sequence's properties
	 * @param $seqrs The sequence RecordSet returned by getSequence()
	 * @param $increment The sequence incremental value
	 * @param $minvalue The sequence minimum value
	 * @param $maxvalue The sequence maximum value
	 * @param $restartvalue The sequence current value
	 * @param $cachevalue The sequence cache value
	 * @param $cycledvalue Sequence can cycle ?
	 * @param $startvalue The sequence start value when issueing a restart
	 * @return 0 success
	 */
	function alterSequenceProps($seqrs, $increment,	$minvalue, $maxvalue,
								$restartvalue, $cachevalue, $cycledvalue, $startvalue) {

		$sql = '';
		/* vars are cleaned in _alterSequence */
		if (!empty($increment) && ($increment != $seqrs->fields['increment_by'])) $sql .= " INCREMENT {$increment}";
		if (!empty($minvalue) && ($minvalue != $seqrs->fields['min_value'])) $sql .= " MINVALUE {$minvalue}";
		if (!empty($maxvalue) && ($maxvalue != $seqrs->fields['max_value'])) $sql .= " MAXVALUE {$maxvalue}";
		if (!empty($restartvalue) && ($restartvalue != $seqrs->fields['last_value'])) $sql .= " RESTART {$restartvalue}";
		if (!empty($cachevalue) && ($cachevalue != $seqrs->fields['cache_value'])) $sql .= " CACHE {$cachevalue}";
		if (!empty($startvalue) && ($startvalue != $seqrs->fields['start_value'])) $sql .= " START {$startvalue}";
		// toggle cycle yes/no
		if (!is_null($cycledvalue))	$sql .= (!$cycledvalue ? ' NO ' : '') . " CYCLE";
		if ($sql != '') {
			$f_schema = $this->_schema;
			$this->fieldClean($f_schema);
			$sql = "ALTER SEQUENCE \"{$f_schema}\".\"{$seqrs->fields['seqname']}\" {$sql}";
			return $this->execute($sql);
		}
		return 0;
	}

	/**
	 * Protected method which alter a sequence
	 * SHOULDN'T BE CALLED OUTSIDE OF A TRANSACTION
	 * @param $seqrs The sequence recordSet returned by getSequence()
	 * @param $name The new name for the sequence
	 * @param $comment The comment on the sequence
	 * @param $owner The new owner for the sequence
	 * @param $schema The new schema for the sequence
	 * @param $increment The increment
	 * @param $minvalue The min value
	 * @param $maxvalue The max value
	 * @param $restartvalue The starting value
	 * @param $cachevalue The cache value
	 * @param $cycledvalue True if cycled, false otherwise
	 * @param $startvalue The sequence start value when issueing a restart
	 * @return 0 success
	 * @return -3 rename error
	 * @return -4 comment error
	 * @return -5 owner error
	 * @return -6 get sequence props error
	 * @return -7 schema error
	 */
	protected
	function _alterSequence($seqrs, $name, $comment, $owner, $schema, $increment,
	$minvalue, $maxvalue, $restartvalue, $cachevalue, $cycledvalue, $startvalue) {

		$this->fieldArrayClean($seqrs->fields);

		// Comment
		$status = $this->setComment('SEQUENCE', $seqrs->fields['seqname'], '', $comment);
		if ($status != 0)
			return -4;

		// Owner
		$this->fieldClean($owner);
		$status = $this->alterSequenceOwner($seqrs, $owner);
		if ($status != 0)
			return -5;

		// Props
		$this->clean($increment);
		$this->clean($minvalue);
		$this->clean($maxvalue);
		$this->clean($restartvalue);
		$this->clean($cachevalue);
		$this->clean($cycledvalue);
		$this->clean($startvalue);
		$status = $this->alterSequenceProps($seqrs, $increment,	$minvalue,
			$maxvalue, $restartvalue, $cachevalue, $cycledvalue, $startvalue);
		if ($status != 0)
			return -6;

		// Rename
		$this->fieldClean($name);
		$status = $this->alterSequenceName($seqrs, $name);
		if ($status != 0)
			return -3;

		// Schema
		$this->clean($schema);
		$status = $this->alterSequenceSchema($seqrs, $schema);
		if ($status != 0)
			return -7;

		return 0;
	}

	/**
	 * Alters a sequence
	 * @param $sequence The name of the sequence
	 * @param $name The new name for the sequence
	 * @param $comment The comment on the sequence
	 * @param $owner The new owner for the sequence
	 * @param $schema The new schema for the sequence
	 * @param $increment The increment
	 * @param $minvalue The min value
	 * @param $maxvalue The max value
	 * @param $restartvalue The starting value
	 * @param $cachevalue The cache value
	 * @param $cycledvalue True if cycled, false otherwise
	 * @param $startvalue The sequence start value when issueing a restart
	 * @return 0 success
	 * @return -1 transaction error
	 * @return -2 get existing sequence error
	 * @return $this->_alterSequence error code
	 */
    function alterSequence($sequence, $name, $comment, $owner=null, $schema=null, $increment=null,
	$minvalue=null, $maxvalue=null, $restartvalue=null, $cachevalue=null, $cycledvalue=null, $startvalue=null) {

		$this->fieldClean($sequence);

		$data = $this->getSequence($sequence);

		if ($data->recordCount() != 1)
			return -2;

		$status = $this->beginTransaction();
		if ($status != 0) {
			$this->rollbackTransaction();
			return -1;
		}

		$status = $this->_alterSequence($data, $name, $comment, $owner, $schema, $increment,
				$minvalue, $maxvalue, $restartvalue, $cachevalue, $cycledvalue, $startvalue);

		if ($status != 0) {
			$this->rollbackTransaction();
			return $status;
		}

		return $this->endTransaction();
	}

	/**
	 * Drops a given sequence
	 * @param $sequence Sequence name
	 * @param $cascade True to cascade drop, false to restrict
	 * @return 0 success
	 */
	function dropSequence($sequence, $cascade) {
		$f_schema = $this->_schema;
		$this->fieldClean($f_schema);
		$this->fieldClean($sequence);

		$sql = "DROP SEQUENCE \"{$f_schema}\".\"{$sequence}\"";
		if ($cascade) $sql .= " CASCADE";

		return $this->execute($sql);
	}
}
?>
