<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use PDO;
use PDOException;

class PostgresNIPSync extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
   // protected $signature = 'postgres:nip-sync {days} {currency_code}';
    protected $signature = 'postgres:nip-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line nip to postgres';


    public function handle()
    {
        echo 'Syncronizing NIP transaction to NIP Buffers..'.PHP_EOL;
        try {
            $this->inward();
            $this->outward();
        } catch (Throwable $ex) {
        }
        //sleep(3);
        echo 'Done!'.PHP_EOL;
    }

    //===============================================================================
    //Inwardward buffer function========================================================

    public function inward()
    {
        set_time_limit(0);
        echo 'Updating Inward Buffer..' . PHP_EOL;
        // all transactions last 2 hours
        //$days = (int)$this->argument('days');
       // $currency_code = $this->argument('currency_code');
       //$days_before = (int)$days + 1;


        try {
      
            $cnt = 0;

            //get the NIP outward transactions==============

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://172.19.1.156/taj/public/index.php/nip/get-inward',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            curl_close($curl);
           // echo $response;

            // Decode the JSON response to an array
            $data = json_decode($response, true);
            // core db
           // while ($row = oci_fetch_assoc($stid)) {
            foreach ($data as $row) {
                $cnt = $cnt + 1;
                echo 'Record No: ' . $cnt . PHP_EOL;
                echo $row['RowID'] . PHP_EOL;
                echo $row['RequestID'] . PHP_EOL;
                echo $row['Amount'] . PHP_EOL;
                echo $row['RecordDate'] . PHP_EOL;
                echo $row['Status'] . PHP_EOL;
                echo $row['TranType'] . PHP_EOL;
                echo $row['ClientAppID'] . PHP_EOL;
               

                $commit = '';
                // commit after every 200 inserts, free up logs and lock resources
                if ($cnt % 200 == 0) {
                    echo 'Threshold reached.. will commit ' . PHP_EOL;
                    $commit = ' COMMIT; ';
                }
                $bind_vars = [
                    "RowID" => $row['RowID'],
                    "RequestID" => $row['RequestID'],
                    "RecordID" => $row['RecordID'],
                    "ChannelCode" => $row['ChannelCode'],
                    "SenderBankCode" => $row['SenderBankCode'],
                    "DestinationBankCode" => $row['DestinationBankCode'],
                    "SourceAccountNo" => $row['SourceAccountNo'],
                    "AccountName" => $row['AccountName'],
                    "DestinationAccountNo" => $row['DestinationAccountNo'],
                    "OriginatorAccountNo" => $row['OriginatorAccountNo'],
                    "OriginatorName" => $row['OriginatorName'],
                    "Narration" => $row['Narration'],
                    "OriginalNarration" => $row['OriginalNarration'],
                    "PaymentReference" => $row['PaymentReference'],
                    "Amount" => $row['Amount'],
                    "ChargeAmount" => $row['ChargeAmount'],
                    "RecordDate" => $row['RecordDate'],
                    "TranType" => $row['TranType'],
                    "TryCount" => $row['TryCount'],
                    "Status" => $row['Status'],
                    "ProcessFlag" => $row['ProcessFlag'],
                    "LastCheckTime" => $row['LastCheckTime'],
                    "Marker" => $row['Marker'],
                    "SopraTranID" => $row['SopraTranID'],
                    "InwardDetails" => $row['InwardDetails'],
                    "CheckSum" => $row['CheckSum'],
                    "ClientAppID" => $row['ClientAppID'],
                    "OriginatorBvn" => $row['OriginatorBvn'],
                    "BeneficiaryBvn" => $row['BeneficiaryBvn'],
                    "ApplyStampDuty" => $row['ApplyStampDuty'],
                    "StampDutyStatus" => $row['StampDutyStatus'],
                    "TransactionQueryStatus" => $row['TransactionQueryStatus'],
                    "TransactionQueryResponse" => $row['TransactionQueryResponse'],
                    "TransactionQueryXml" => $row['TransactionQueryXml'],
                    "TransactionStatusLastCheckTime" => $row['TransactionStatusLastCheckTime'],
                    "TransactionStatusTryCount" => $row['TransactionStatusTryCount'],
                ];
                   
                // Establish a connection to PostgreSQL
                try {
                    $dsn = "pgsql:host=127.0.0.1;dbname=tajbank";
                    $username = "postgres";
                    $password = "Tajbank123_";

                    $p_conn = new PDO($dsn, $username, $password);
                    $p_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Now you can call the pg_db_execute function with the $p_conn object
                    $this->pg_db_execute($p_conn,  "INSERT INTO NIP_INWARD_BUFFER(
                  ROW_ID,REQUESTID,RECORDID,CHANNELCODE,SENDERBANKCODE,DESTINATIONBANKCODE,SOURCEACCOUNTNO,DESTINATIONACCOUNTNO,ACCOUNTNAME,ORIGINATORACCOUNTNO,
                  ORIGINATORNAME,NARRATION,ORIGINALNARRATION,PAYMENTREFERENCE,AMOUNT,CHARGEAMOUNT,
                  RECORDDATE,TRANTYPE,TRYCOUNT,STATUS,PROCESSFLAG,LASTCHECKTIME,MARKER,SOPRATRANID,INWARDDETAILS,CHECKSUM,CLIENTAPPID,
                  ORIGINATORBVN,BENEFICIARYBVN,APPLYSTAMPDUTY,STAMPDUTYSTATUS,TRANSACTIONQUERYSTATUS,TRANSACTIONQUERYRESPONSE,TRANSACTIONQUERYXML,TRANSACTIONSTATUSLASTCHECKTIME,TRANSACTIONSTATUSTRYCOUNT
                        )
                        VALUES (
                            :RowID,:RequestID,:RecordID,:ChannelCode,:SenderBankCode,:DestinationBankCode,:SourceAccountNo,
                            :DestinationAccountNo,:AccountName,:OriginatorAccountNo,:OriginatorName,:Narration,:OriginalNarration,:PaymentReference,:Amount,:ChargeAmount,:RecordDate,:TranType,
                            :TryCount,:Status,:ProcessFlag,:LastCheckTime,:Marker,:SopraTranID,:InwardDetails,:CheckSum,
                            :ClientAppID,:OriginatorBvn,:BeneficiaryBvn,:ApplyStampDuty,:StampDutyStatus,:TransactionQueryStatus,:TransactionQueryResponse,:TransactionQueryXml,:TransactionStatusLastCheckTime,:TransactionStatusTryCount
                        );" . $commit, $bind_vars);
                    echo '1 row inserted ' . PHP_EOL;
                } catch (PDOException $e) {
                    echo "Connection failed: " . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    } 

    //===============================================================================
    //Outward buffer function========================================================

    public function outward()
    {
        set_time_limit(0);
        echo 'Updating Outward Buffer..' . PHP_EOL;
        // all transactions last 2 hours
        //$days = (int)$this->argument('days');
       // $currency_code = $this->argument('currency_code');
       //$days_before = (int)$days + 1;


        try {
      
            $cnt = 0;

            //get the NIP outward transactions==============

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://172.19.1.156/taj/public/index.php/nip/get-outward',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            //echo $response;

            // Decode the JSON response to an array
            $data = json_decode($response, true);
            // core db
           // while ($row = oci_fetch_assoc($stid)) {
            foreach ($data as $row) {
                $cnt = $cnt + 1;
                echo 'Record No: ' . $cnt . PHP_EOL;
                echo $row['RowID'] . PHP_EOL;
                echo $row['RequestID'] . PHP_EOL;
                echo $row['Amount'] . PHP_EOL;
                echo $row['PostedDate'] . PHP_EOL;
                echo $row['Status'] . PHP_EOL;
                echo $row['TranType'] . PHP_EOL;
                echo $row['ClientAppID'] . PHP_EOL;
               

                $commit = '';
                // commit after every 200 inserts, free up logs and lock resources
                if ($cnt % 200 == 0) {
                    echo 'Threshold reached.. will commit ' . PHP_EOL;
                    $commit = ' COMMIT; ';
                }
                $bind_vars = [
                    "ROWID" => $row['RowID'],
                    "RequestID" => $row['RequestID'],
                    "RecordID" => $row['RecordID'],
                    "TraceID" => $row['TraceID'],
                    "DestinationInstitutionCode" => $row['DestinationInstitutionCode'],
                    "ChannelCode" => $row['ChannelCode'],
                    "TAJSourceAccountNo" => $row['TAJSourceAccountNo'],
                    "TAJDestinationAccountNo" => $row['TAJDestinationAccountNo'],
                    "InterBankDestinationAccountNo" => $row['InterBankDestinationAccountNo'],
                    "Amount" => $row['Amount'],
                    "ChargeAmount" => $row['ChargeAmount'],
                    "Narration" => $row['Narration'],
                    "Status" => $row['Status'],
                    "Response" => $row['Response'],
                    "TryCount" => $row['TryCount'],
                    "PostedDate" => $row['PostedDate'],
                    "ReverseFlag" => $row['ReverseFlag'],
                    "ReverseDate" => $row['ReverseDate'],
                    "ReverseTranID" => $row['ReverseTranID'],
                    "LastCheckTime" => $row['LastCheckTime'],
                    "TranID" => $row['TranID'],
                    "TranType" => $row['TranType'],
                    "TranRemarks" => $row['TranRemarks'],
                    "InterBankDetails" => $row['InterBankDetails'],
                    "ClientAppID" => $row['ClientAppID'],
                    "OriginatorBvn" => $row['OriginatorBvn'],
                    "BeneficiaryBvn" => $row['BeneficiaryBvn'],
                    "BeneficiaryAccountName" => $row['BeneficiaryAccountName'],
                ];
                   

                // Establish a connection to PostgreSQL
                try {
                    $dsn = "pgsql:host=127.0.0.1;dbname=tajbank";
                    $username = "postgres";
                    $password = "Tajbank123_";

                    $p_conn = new PDO($dsn, $username, $password);
                    $p_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Now you can call the pg_db_execute function with the $p_conn object
                    $this->pg_db_execute($p_conn,  "INSERT INTO NIP_OUTWARD_BUFFER(
                        ROW_ID,
                        AMOUNT,
                        STATUS,
                        TRANID,
                        TRACEID,
                        RECORDID,
                        RESPONSE,
                        TRANTYPE,
                        TRYCOUNT,
                        NARRATION,
                        REQUESTID,
                        POSTEDDATE,
                        CHANNELCODE,
                        CLIENTAPPID,
                        REVERSEFLAG,
                        TRANREMARKS,
                        CHARGEAMOUNT,
                        ORIGINATORBVN,
                        REVERSETRANID,
                        BENEFICIARYBVN,
                        INTERBANKDETAILS,
                        TAJSOURCEACCOUNTNO,
                        BENEFICIARYACCOUNTNAME,
                        TAJDESTINATIONACCOUNTNO,
                        DESTINATIONINSTITUTIONCODE,
                        INTERBANKDESTINATIONACCOUNTNO,
                        REVERSEDATE,
                        LASTCHECKTIME)
                        VALUES (
                            :ROWID,
                            :Amount,
                            :Status,
                            :TranID,
                            :TraceID,
                            :RecordID,
                            :Response,
                            :TranType,
                            :TryCount,
                            :Narration,
                            :RequestID,
                            to_timestamp(:PostedDate, 'YYYY-MM-DD HH24:MI:SS.FF3'),
                            :ChannelCode,
                            :ClientAppID,
                            :ReverseFlag,
                            :TranRemarks,
                            :ChargeAmount,
                            :OriginatorBvn,
                            :ReverseTranID,
                            :BeneficiaryBvn,
                            :InterBankDetails,
                            :TAJSourceAccountNo,
                            :BeneficiaryAccountName,
                            :TAJDestinationAccountNo,
                            :DestinationInstitutionCode,
                            :InterBankDestinationAccountNo,
                            :ReverseDate,
                            :LastCheckTime
                        );" . $commit, $bind_vars);
                    echo '1 row inserted ' . PHP_EOL;
                } catch (PDOException $e) {
                    echo "Connection failed: " . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }


    private function pg_db_execute($conn, $sql, $bind = null)
    {
        $stmt = $conn->prepare($sql);

        if ($bind) {
            foreach ($bind as $key => $value) {
                $stmt->bindValue(':' . $key, htmlspecialchars($value, ENT_QUOTES));
            }
        }

        $data = $stmt->execute();
        $stmt->closeCursor();

        return $data;
    }
}
