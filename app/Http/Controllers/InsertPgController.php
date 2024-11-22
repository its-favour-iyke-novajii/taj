<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;

class InsertPgController extends BaseController
{

    public function amlInsert(Request $request)
    {
        try {
            $host = '127.0.0.1';
            $port = '5432';
            $database = 'tajbank';
            $username = 'postgres';
            $password = 'Tajbank123_';

            $jsonContent = file_get_contents('php://input');
            $data = json_decode($jsonContent, true);

            if (!$data) {
                //return response()->json(['error' => 'Invalid JSON data'], 400);
                echo 'Invalid JSON data' ;
            }

            $pdo = new \PDO("pgsql:host=$host;port=$port;dbname=$database;user=$username;password=$password");

            // Prepare and execute the SQL query
            $stmt = $pdo->prepare("
                INSERT INTO AML_REPORT (name, user_id, report_type_id, xml_data, status)
                VALUES (:name, :user_id, :report_type_id, :xml_data, :status)
            ");

            $stmt->bindParam(':name', $data['NAME']);
            $stmt->bindParam(':user_id', $data['USER_ID']);
            $stmt->bindParam(':report_type_id', $data['REPORT_TYPE_ID']);
            $stmt->bindParam(':xml_data', $data['XML_DATA']);
            $stmt->bindParam(':status', $data['STATUS']);

            if ($stmt->execute()) {
                //return response()->json(['message' => 'Data inserted successfully']);
                echo 'Data Inserted successfully';
            } else {
                //return response()->json(['error' => 'Failed to insert data'], 500);
                echo 'fail to insert';
            }
        } catch (\Exception $e) {
           // return response()->json(['error' => $e->getMessage()], 500);
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }
}
