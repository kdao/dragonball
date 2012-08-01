<?php
if(!defined('sugarEntry'))define('sugarEntry', true);
if(!empty($argv[1])){
    chdir($argv[1]);
    echo "\nChanging Directory To: {$argv[1]} \n\n";
}

$outputCSVFile = "";
$monthCSVFile = "";
foreach ( $argv as $arg ) {
    if (substr($arg, 0, 2) == '-o') {
	$tokens = explode("=", $arg);
	$outputCSVFile = $tokens[1];
    }
    if (substr($arg, 0, 2) == '-m') {
	$tokens = explode("=", $arg);
	$monthCSVFile = $tokens[1];
    }
}

require_once('include/entryPoint.php');
require_once('include/utils.php');
require_once('config.php');
require_once('include/modules.php');
require_once('include/database/DBManagerFactory.php');
require_once('include/SugarTheme/SugarTheme.php');

set_time_limit(0);

class FakeLogger { public function __call($m, $a) { } }

$GLOBALS['log']= new FakeLogger();
$GLOBALS['app_list_strings'] = return_app_list_strings_language('en_us');
$GLOBALS['db'] = DBManagerFactory::getInstance(); // get default sugar db

if(file_exists('custom/application/Ext/Include/modules.ext.php')) {
    require_once('custom/application/Ext/Include/modules.ext.php');
}
if (file_exists('include/modules_override.php')) {
    require_once('include/modules_override.php');
}

class Goku {

    protected $bean = null;

    protected $skip = null;

    function __construct( $module ) {
        $this->module = $module;
        $this->bean = BeanFactory::getBean( $module );
        if( !isset( $this->bean->table_name ) ){
            $this->skip = true;
        }
    }

    /*
    * return bean
    */
    function getBean() {
	return $this->bean;
    }

    function isSkip() {
	return $this->skip;
    }

    /*
    * Returns the number of records for a given module
    */
    function getRecordCount() {
        $result = $GLOBALS['db']->query( 'SELECT COUNT(1) AS count FROM ' . $this->bean->table_name );
        $row = $GLOBALS['db']->fetchByAssoc( $result );
        return isset( $row['count'] ) ? $row['count'] : 0;

    }

    /*
    * Returns the number of records for a given module
    */
    function getLiveRecordCount() {
        $query = 'SELECT COUNT(1) AS count FROM ' . $this->bean->table_name;
        if( !empty( $this->bean->field_defs['deleted'] ) ) {
            $query .= ' WHERE deleted = 0';
        }
        $result = $GLOBALS['db']->query( $query );
        $row = $GLOBALS['db']->fetchByAssoc( $result );
        return isset( $row['count'] ) ? $row['count'] : 0;

    }

    /*
    * returns the average number of records per user
    */
    function getAverageRecordsPerUser() {
	$user_field = ( !empty( $this->bean->field_defs['assigned_user_id'] ) ) ? 'assigned_user_id' : 'created_by';
	$result =  $GLOBALS['db']->query( "SELECT sum(c.count)/count(1) AS average FROM (SELECT {$user_field}, COUNT(1) AS count FROM {$this->bean->table_name} GROUP BY {$user_field}) AS c" );
	$row = $GLOBALS['db']->fetchByAssoc( $result );
	return isset( $row['average'] ) ? $row['average'] : 0;
    }

    /*
    * returns the count of the records created per month
    */
    function getRecordsCreatedPerMonth() {
	$recordCreatedCounts = array();
	$result = $GLOBALS['db']->query( "SELECT DATE_FORMAT(date_entered, '%Y-%m') AS month FROM {$this->bean->table_name} ORDER BY date_entered DESC" );
	while ( $row = $GLOBALS['db']->fetchByAssoc( $result ) ) {
	    $month = $row['month'];
	    if ( !isset( $recordCreatedCounts[$month] ) ) {
		$recordCreatedCounts[$month] = 0;
	    }
	    $recordCreatedCounts[$month]++;
	}
	return $recordCreatedCounts;

    }



    /*
    * returns the count of the records modified per month
    */
    function getRecordsModifiedPerMonth() {
	$recordModifiedCounts = array();
	$result = $GLOBALS['db']->query( "SELECT DATE_FORMAT(date_modified, '%Y-%m') AS month FROM {$this->bean->table_name} ORDER BY date_modified DESC" );
	while ( $row = $GLOBALS['db']->fetchByAssoc( $result ) ) {
	    $month = $row['month'];
	    if ( !isset( $recordModifiedCounts[$month] ) ) {
		$recordModifiedCounts[$month] = 0;
	    }
	    $recordModifiedCounts[$month]++;
	}
	return $recordModifiedCounts;
    }

    /*
    * returns the number of custom fields on this module
    */
    function getNumberOfCustomFields() {
	$customFieldCount = 0;
	if ( $this->bean->field_defs ) {
	    foreach ( $this->bean->field_defs as $field=>$info ) {
		if ( isset( $info['source'] ) && $info['source'] == 'custom_fields' ) {
		    $customFieldCount++;
		}
	    } 
	}
	return $customFieldCount; 
    }


    /*
    * returns the number of users that are assigned records in this module
    */
    function getNumberOfUsers() {
	$user_field = !empty( $this->bean->field_defs['assigned_user_id'] ) ? 'assigned_user_id' : 'created_by';
	$result = $GLOBALS['db']->query( "SELECT count(DISTINCT {$user_field}) AS count FROM {$this->bean->table_name}" );
	$row = $GLOBALS['db']->fetchByAssoc( $result );
	return isset( $row['count'] ) ? $row['count'] : 0;
    }

    /*
    * returns the number of audit records <table_name>_audit
    */
    function getNumberOfAuditRecords() {
	$auditTable = $this->bean->get_audit_table_name();
	$query = "SELECT COUNT(1) AS count FROM {$auditTable} ";
	$result = $GLOBALS['db']->query( $query );
	$row = $GLOBALS['db']->fetchByAssoc( $result );
	return isset( $row['count'] ) ? $row['count'] : 0;
    }


    /*
    *returns the number of custom records  <table_name>_cstm
    */
    function getNumberOfCustomRecords() {
	$customTable = $this->bean->get_custom_table_name();
	$query = "SELECT COUNT(1) AS count FROM {$customTable} ";
	$result = $GLOBALS['db']->query( $query );
	$row = $GLOBALS['db']->fetchByAssoc( $result );
	return isset( $row['count'] ) ? $row['count'] : 0;
    }


    /*
    * Formats the human readable strings of records creatd and modified per month
    */
    function formatRecords( $recordsCreatedPerMonth, $recordsModifiedPerMonth ) {
	$records = $this->getCombinedRecords( $recordsCreatedPerMonth, $recordsModifiedPerMonth );
	
	// format 
	$recordsStr = "";
	foreach( $records as $month=>$info ) {
	    $recordsStr .= $month . " Created: " . $records[$month]['created'] . " Modified: " . $records[$month]['modified'] . "\n";
	}

	return $recordsStr;
    }

    /*
    *
    */
    function getCombinedRecords( $recordsCreatedPerMonth, $recordsModifiedPerMonth ) {
	$records = array();
	foreach ( $recordsCreatedPerMonth as $month=>$count ) {
	    $records[$month]['created'] = $count;
	}
	foreach ( $recordsModifiedPerMonth as $month=>$count ) {
	    $records[$month]['modified'] = $count;
	}
	// file in the blanks
	foreach ( $records as $month=>$info ) {
	    if( !isset( $records[$month]['created'] ) ) $records[$month]['created'] = 0;
	    if( !isset( $records[$month]['modified'] ) ) $records[$month]['modified'] = 0;
	    if( !isset( $records[$month]['monthStr'] ) ) $records[$month]['monthStr'] = str_replace('-', '', $month);
	}
    
	$compareField = array();
	foreach ( $records as $month=>$info ) {
	    $compareField[] = $records[$month]['monthStr'];
	}
	array_multisort( $compareField, SORT_DESC, $records ); // sort recent first
	return $records;
    }

    /*
    *
    */
    function toString( $records ) {
	$rtnStr = "";
	foreach ( $records as $month=>$count ) {
	    $rtnStr .= $month . " " . $count . " | ";
	}
	$rtnStr = substr( $rtnStr, 0, -3 );
	return $rtnStr;
    }


    /*
    * Executes all functions and print out the report
    */ 
    function execute() {
        if( $this->skip ) {
            echo "\n\n\n\n\nSKIPPING $this->module ***************************\n\n\n\n\n";
	    return;
        }
        return <<<EOQ
        
Module: {$this->module}
Table: {$this->bean->table_name}
Custom Fields: {$this->getNumberOfCustomFields()}
Total Records: {$this->getRecordCount()}
Live Records: {$this->getLiveRecordCount()}
Audit Records: {$this->getNumberOfAuditRecords()}
Custom Records: {$this->getNumberOfCustomRecords()}
Number of Users: {$this->getNumberOfUsers()}
Avg Records Per User: {$this->getAverageRecordsPerUser()}
{$this->formatRecords( $this->getRecordsCreatedPerMonth(), $this->getRecordsModifiedPerMonth() )}

EOQ;

    }

}


class DragonBall {


    /*
    * Gives the reports
    */
    function scan() {
	echo ("\n");
        foreach( $GLOBALS['beanList'] as $module=>$bean ) {
            $goku = new Goku( $module );
            echo $goku->execute();
        }
    }


    /*
    * put to csv file
    */
    function putCSVFile( $outputCSVFile ) {
	$list = array(
	    array('MODULE', 'TABLE', 'CUSTOM_FIELDS', 'TOTAL_RECORDS', 'LIVE_RECORDS', 'AUDIT_RECORDS', 'CUSTOM_RECORDS', 'NUMBER_OF_USERS', 'AVG_RECRODS/USER', 'RECORDSCREATED/MONTH', 'RECORDSMODIFIED/MONTH')
	);
	foreach( $GLOBALS['beanList'] as $module=>$bean ) {
	    $goku = new Goku( $module );
	    if ( !$goku->isSkip() ) {
		$list[] = array( 
		    $goku->module,
		    $goku->getBean()->table_name,
		    $goku->getNumberOfCustomFields(),
		    $goku->getRecordCount(),
		    $goku->getLiveRecordCount(),
		    $goku->getNumberOfAuditRecords(),
		    $goku->getNumberOfCustomRecords(),
		    $goku->getNumberOfUsers(),
		    $goku->getAverageRecordsPerUser(),
		    $goku->toString($goku->getRecordsCreatedPerMonth()),
		    $goku->toString($goku->getRecordsModifiedPerMonth()),
		);
	    }
	}

	$fp = fopen( $outputCSVFile, 'w' );

	if (function_exists( 'fputcsv' ) ) {;
	    foreach ( $list as $record ) {
		fputcsv( $fp, $record );
	    }
	} else {
	    foreach ( $list as $record ) {
		fwrite( $fp, implode(", ", $record ) . "\n" );
	    }
	}

	fclose( $fp );

    }

    /*
    * Returns activities by month
    */
    function putActivitiesByMonthCSVFile( $monthCSVFile ) {
	$list = array(
	    array('MONTH', 'RECORDS_CREATED', 'RECORDS_MODIFIED', 'MODULE' ),
	);
	foreach ( $GLOBALS['beanList'] as $module=>$bean ) {
	    $goku = new Goku( $module );
	    if ( !$goku->isSkip() ) {
		$combinedRecord = $goku->getCombinedRecords( $goku->getRecordsCreatedPerMonth(), $goku->getRecordsModifiedPerMonth() );
		foreach ( $combinedRecord as $month=>$activities ) {
		    $list[] = array (
			$month,
			$combinedRecord[$month]['created'],
			$combinedRecord[$month]['modified'],
			$goku->module,
		    );
		}
	    }
	}

	$fp = fopen( $monthCSVFile, 'w' );

	if (function_exists( 'fputcsv' ) ) {;
	    foreach ( $list as $record ) {
		fputcsv( $fp, $record );
	    }
	} else {
	    foreach ( $list as $record ) {
		fwrite( $fp, implode(", ", $record ) . "\n" );
	    }
	}

	fclose( $fp );

    }




}

echo("\n");
$dragonball = new DragonBall();
if ( $outputCSVFile ) {
    $dragonball->putCSVFile( $outputCSVFile );
}
if ( $monthCSVFile ) {
    $dragonball->putActivitiesByMonthCSVFile( $monthCSVFile );
}
$dragonball->scan();
echo("\n");
