<?php
if(!defined('sugarEntry'))define('sugarEntry', true);
if(!empty($argv[1])){
    chdir($argv[1]);
    echo "\nChanging Directory To: {$argv[1]}";
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
    * Returns the number of records for a given module
    */
    function recordCount() {
        $result = $GLOBALS['db']->query( 'SELECT count(1) as count FROM ' . $this->bean->table_name );
        $row = $GLOBALS['db']->fetchByAssoc( $result );
        return $row['count'];

    }

    /*
    * Returns the number of records for a given module
    */
    function liveRecordCount(){
        $query = 'SELECT count(1) as count FROM ' . $this->bean->table_name;
        if( !empty( $this->bean->field_defs['deleted'] ) ) {
            $query .= ' WHERE deleted = 0';
        }
        $result = $GLOBALS['db']->query( $query );
        $row = $GLOBALS['db']->fetchByAssoc( $result );
        return $row['count'];

    }

    /*
    * returns the average number of records per user
    */
    function averageRecordsPerUser(){

    }

    /*
    * returns the count of the records created per month
    */
    function recordsCreatedPerMonth() {

    }



    /*
    * returns the count of the records modified per month
    */
    function recordsModifiedPerMonth() {

    }

    /*
    * returns the number of custom fields on this module
    */
    function numberOfCustomFields() {

    }


    /*
    * returns the number of users that are assigned records in this module
    */
    function numberOfUsers() {

    }

    /*
    * returns the number of audit records <table_name>_audit
    */
    function numberOfAuditRecords() {


    }


    /*
    *returns the number of custom records  <table_name>_cstm
    */
    function numberOfCustomRecords() {

    }


    function execute() {
        if( $this->skip ) {
            echo "\n\n\n\n\nSKIPPING $this->module ***************************\n\n\n\n\n";
        }
        $totalRecords = $this->recordCount();
        $liveRecords = $this->liveRecordCount();
        return <<<EOQ
        
Module: $this->module
Table: {$this->bean->table_name}
Custom Fields: 12
Total Records: $totalRecords
Live Records: $liveRecords
Audit Records: 700
Custom Records: 180
Number of Users: 200
Avg Records Per User: 5
Jan 2011 Created:12 Modified:36
Feb 2011 Created:13 Modified:26
EOQ;

    }






}
class DragonBall{

    function scan() {
        foreach( $GLOBALS['beanList'] as $module=>$bean ) {
            $goku = new Goku( $module );
            echo $goku->execute();
        }
    }



}

$dragonball = new DragonBall();
$dragonball->scan();
