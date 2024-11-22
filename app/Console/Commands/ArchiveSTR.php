<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class ArchiveSTR extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'archive:str';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line Archive STR ';

    public function handle()
    {
        putenv('LD_LIBRARY_PATH=/usr/lib/oracle/19.5/client64/lib:/lib:/usr/lib');
        set_time_limit(0);
        echo 'Running STR Update..' . PHP_EOL;
        
        // Assuming the PostgreSQL database connection details
        $host = "172.19.2.86";
        $port = "5432";
        $dbname = "tajbank";
        $username = "postgres";
        $password = "Tajbank123_";

        try {
            // Connect to PostgreSQL
            $pgsqlConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
            $pgsqlConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Update records in the 'str' table
            $sql =  "UPDATE str SET status = '5' WHERE DATE_TRUNC('day', created_at) < CURRENT_DATE AND status = '1'";
  
            // Execute the SQL statement
            $pgsqlConn->exec($sql);

            echo "Updated records in 'str' table.\n";

        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        echo 'Done!' . PHP_EOL;
    }
}
