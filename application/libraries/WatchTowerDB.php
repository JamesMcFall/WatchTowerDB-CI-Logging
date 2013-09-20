<?php

/*
 * WatchTower DB Logger - Easier Database Driven CI logging.
 * 
 * WatchTowerDB is a database driven version of WatchTower Logging 
 * (https://github.com/JamesMcFall/WatchTower-CI-Logging). Instead of using log
 * files the errors are written to the database.
 * 
 * The whole purpose of WatchTowerDB is to provide an easy means to set up and 
 * write to a number of various database stored logs in a consistent and easy way.
 * 
 * The two main points of WatchTowerDB are:
 * - Writing to database logs.
 * - Optionally notifying a list of email addresses if it's a SHTF situation.
 * 
 * @author James McFall <james@mcfall.geek.nz>
 * @version 0.1.1
 */

class WatchTowerDB {

    /**
     * Class Properties
     */
    private $_conf          = null; # Stores the config file watchtower settings
    private $_ci            = null; # Instance of CI
    private $_timeFormat    = "Y-m-d G:i:s";
    public $tableStreams    = "WatchTowerLogStreams";
    public $tableLogs       = "WatchTowerLogs";

    
    /**
     * Constructor
     * 
     * Check the database tables exist and if not, create them.
     * 
     * @return <void>
     */
    public function __construct() {
        $this->_ci = &get_instance();

        # If the database tables don't exist for the logging, set them up.
        if (!$this->_checkDBTables()) {
            $this->_setUpTables();
        }
    }

    
    /**
     * Check that the database tables exist.
     * 
     * @return <boolean>
     */
    private function _checkDBTables() {

        $databaseName = $this->_ci->db->database;
        $streamsExists = $logsExists = false;

        # Check if streams table exists.
        $streamsResult = $this->_ci->db->query("
            SELECT * 
            FROM information_schema.tables
            WHERE table_schema = '<?=$databaseName?>' 
                AND table_name = '<?=$this->tableStreams?>'
            LIMIT 1;
        ");

        if ($streamsResult->num_rows() > 0)
            $streamsExists = true;


        # Check if logs table exists
        $logsResult = $this->_ci->db->query("
            SELECT * 
            FROM information_schema.tables
            WHERE table_schema = '<?=$databaseName?>' 
                AND table_name = '<?=$this->tableLogs?>'
            LIMIT 1;
        ");

        if ($logsResult->num_rows() > 0)
            $logsExists = true;

        # If we've got both tables, it's already set up and good.
        if ($logsExists && $streamsExists)
            return true;

        # One or more tables weren't found. Have to create the tables.
        return false;
    }

    
    /**
     * This method creates the tables used for storing the log information
     * 
     * @return <void>
     */
    private function _setUpTables() {

        # Load up DB Forge so we can create the database tables
        $this->_ci->load->dbforge();

        # Set up the stream table fields
        $streamFields = array(
            'stream_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'stream_name' => array(
                'type' => 'VARCHAR',
                'constraint' => '255',
            ),
            'stream_description' => array(
                'type' => 'TEXT',
                'null' => TRUE,
            ),
            'stream_notify' => array(
                'type' => 'VARCHAR',
                'constraint' => '255',
            ),
        );
        
        # Set up the streams tabls
        $this->_ci->dbforge->add_field($streamFields);
        $this->_ci->dbforge->add_key('stream_id', TRUE); # Set the primary key
        $this->_ci->dbforge->create_table($this->tableStreams, true); # True for if not exists clausecodei 
        
        
         # Set up the log table fields
        $logFields = array(
            'log_entry_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'stream_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE
            ),
            'log_time' => array(
                'type' => 'DATETIME'
            ),
            'log_message' => array(
                'type' => 'TEXT',
                'null' => FALSE,
            ),
        );
        
        # Set up the log table
        $this->_ci->dbforge->add_field($logFields);
        $this->_ci->dbforge->add_key('log_entry_id', TRUE); # Set the primary key
        $this->_ci->dbforge->create_table($this->tableLogs, true);
    }

    
    /**
     * Write message to a log file
     * 
     * This method will write the supplied message to the specified log file 
     * (stream) using the current server time in the log. 
     * 
     * @param <string> $stream - The log stream
     * @param <string> $message - The error message
     * @param <boolean> $notify  - Whether to notify the person specified in 
     *                             the config file.
     * @return <void>
     */
    public function log($stream, $message, $notify = false) {

        $time = new DateTime();

        # Get the stream for this log. If it doesn't exist, make it.
        $streamResult = $this->_ci->db->get_where($this->tableStreams, array("stream_name" => $stream));
        if ($streamResult->num_rows() == 0) {
            # Create it, then immediatly pull it from the DB again.
            $this->_ci->db->insert($this->tableStreams, array(
                "stream_name" => $stream,
                "stream_description" => ""));
            
            # Save a DB call. Fake the db row.
            $streamResult = new stdClass();
            $streamResult->stream_id = $this->_ci->db->insert_id();
            $streamResult->stream_notify = ""; # We just created it so no one to notify yet.
        } else {
            # Got the stream from the DB
            $streamResult = $streamResult->row();
        }
        
                
        # Set up the log info
        $logData = array(
            "log_time"    => $time->format($this->_timeFormat),
            "log_message" => $message,
            "stream_id" => $streamResult->stream_id
        );
        
        # Insert a log entry for this stream
        $this->_ci->db->insert($this->tableLogs, $logData);

        # If the notify flag is set, get this streams assigned notifyee
        if ($notify === true) {
            
            # Find who to notify on this stream
            if (strlen($streamResult->stream_notify) == "") {
                throw new Exception("No one set to be notified for " . $stream . " stream");
            } else {
                # Send out the email
                $this->_sendNotificationEmail($time, $message, $streamResult->stream_notify);
            }
        }
    }

    
    /**
     * Send Notification Email
     * 
     * This method builds a very basic html email to send to the people in the
     * notify array in the config file. Basic fallback to non-html is just the
     * log line entry.
     * 
     * @param <DateTime> $time
     * @param <string> $message
     * @param <string> $notifyWho
     * @return <boolean> 
     */
    private function _sendNotificationEmail($time, $message, $notifyWho) {

        # If the email library is not loaded, load it up
        if (!isset($this->_ci->email)) {
            $this->_ci->load->library('email');
        }

        $timeString = $time->format($this->_timeFormat);

        # Build the basic HTML message for the email
        $htmlMessage = "<h3>WatchTower Notification: " . $_SERVER['HTTP_HOST'] . "</h3>";
        $htmlMessage .= "<b>Time:</b> " . $timeString . "<br /><br />";
        $htmlMessage .= "<b>Message:</b> " . $message;

        # Not sure if this is required, but set the mailtype to HTML
        $this->_ci->email->initialize(array('mailtype' => 'html'));

        # Set up message details
        $this->_ci->email->from("watchtower@" . $_SERVER['HTTP_HOST'], "WatchTower Logger");
        $this->_ci->email->reply_to("watchtower@" . $_SERVER['HTTP_HOST']);
        $this->_ci->email->subject("WatchTower Notification: " . $_SERVER['HTTP_HOST']);
        $this->_ci->email->message($htmlMessage);
        $this->_ci->email->set_alt_message($timeString . " - " . $message);

        # Notify who
        $this->_ci->email->to($notifyWho);

        return $this->_ci->email->send();
    }

    
    /**
     * Dump a variable into the outpub buffer and catch it as a string.
     * 
     * @param <any> $var
     * @return <string> 
     */
    public function dumpVarToString($var) {
        ob_start();
        var_dump($var);
        return "\n\n" . ob_get_clean();
    }

}

?>