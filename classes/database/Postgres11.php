<?php

/**
 * A Class that implements the DB Interface for Postgres
 * Note: This Class uses ADODB and returns RecordSets.
 *
 * $Id: Postgres.php,v 1.320 2008/02/20 20:43:09 ioguix Exp $
 */

include_once('./classes/database/Postgres.php');

class Postgres11 extends Postgres12 {
	var $major_version = 11.0;

	/**
	 * Constructor
	 * @param $conn The database connection
	 */
	function __construct($conn) {
		parent::__construct($conn);
	}
	
    /**
	 * Checks to see whether or not a table has a unique id column
	 * @param $table The table name
	 * @return True if it has a unique id, false otherwise
	 * @return null error
	 **/
	function hasObjectID($table) {
		$c_schema = $this->_schema;
		$this->clean($c_schema);
		$this->clean($table);

		$sql = "SELECT relhasoids FROM pg_catalog.pg_class WHERE relname='{$table}'
			AND relnamespace = (SELECT oid FROM pg_catalog.pg_namespace WHERE nspname='{$c_schema}')";

		$rs = $this->selectSet($sql);
		if ($rs->recordCount() != 1) return null;
		else {
			$rs->fields['relhasoids'] = $this->phpBool($rs->fields['relhasoids']);
			return $rs->fields['relhasoids'];
		}
	}
}
?>
