<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class PostgresQueryDbController extends BaseController
{
    public function query(Request $request)
    {
        try {
            $host = '127.0.0.1';
            $port = '5432';
            $database = 'tajbank';
            $username = 'postgres';
            $password = 'Tajbank123_';
            
            // Establish a connection to PostgreSQL
            $connString = "host=$host port=$port dbname=$database user=$username password=$password";
            $conn = pg_connect($connString);
            if (!$conn) {
                throw new \Exception('Connection failed: ' . pg_last_error());
            }

            // Get the SQL query from the request header
            //$sql = $request->header('sql');

            $sql = file_get_contents("php://input");

            // Execute the query
            $result = pg_query($conn, $sql);
            if (!$result) {
                throw new \Exception('Query execution failed: ' . pg_last_error($conn));
            }

            // Fetch all rows as an associative array
            $rows = pg_fetch_all($result);

            // Close the connection
            pg_close($conn);

            return response()->json($rows);
        } catch (\Exception $e) {
            // Handle the exception or error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function update(Request $request)
    {
        try {
            $host = '127.0.0.1';
            $port = '5432';
            $database = 'tajbank';
            $username = 'postgres';
            $password = 'Tajbank123_';
            
            // Establish a connection to PostgreSQL
            $connString = "host=$host port=$port dbname=$database user=$username password=$password";
            $conn = pg_connect($connString);
            if (!$conn) {
                throw new \Exception('Connection failed: ' . pg_last_error());
            }
    
            // Get the SQL query from the request header
            //$sql = $request->header('sql');
    
            $sql = file_get_contents("php://input");
    
            // Execute the update query
            $result = pg_query($conn, $sql);
            if (!$result) {
                throw new \Exception('Query execution failed: ' . pg_last_error($conn));
            }
    
            // Close the connection
            pg_close($conn);
    
            return response()->json(['message' => 'Update successful']);
        } catch (\Exception $e) {
            // Handle the exception or error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    





}
