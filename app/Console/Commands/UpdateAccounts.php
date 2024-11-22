<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class UpdateAccounts extends Command
{
    protected $signature = 'update:accounts';

    public function handle()
    {
       $this->update_corporate();
        //$this->update_pep();
       // $this->update_customers();
    }

    public function update_corporate()
    {
        try {
            $host = '172.19.20.60';
            $user = 'novaji';
            $pwd = 'novali123';
            $port = '1521';
            $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
            $oracle_conn = oci_connect($user, $pwd, "172.19.20.60:$port/tajrep");

            $oracle_query = "SELECT
            a.cli AS CUSTOMER_ID,
            (SELECT TRIM(lib) FROM tajprod.bkage WHERE TRIM(age) = TRIM(b.age)) AS BRANCH,
            a.cdir AS DIRECTORID,
            a.pre AS FIRSTNAME,
            a.nom AS LASTNAME,
            b.ncp AS ACCOUNTNUMBER,
            (SELECT c.vala FROM tajprod.bkitier c WHERE c.iden='001' AND c.ctie = a.cdir AND rownum=1) AS BVN,
            (SELECT d.nomrest FROM tajprod.bkcli d WHERE d.cli=a.cli AND rownum=1) AS COMPANYNAME,
            b.dou AS DATE_OPENED,
            TO_CHAR(b.crt, '999,999,999.99') AS CREDITED,
            TO_CHAR(b.dbt, '999,999,999.99') AS Debited,
            TO_CHAR(b.sin, '999,999,999.99') AS BALANCE,
            b.typ TYPE, 
            b.cha GL_CODE
            FROM tajprod.BKDIRCLI a,
            tajprod.bkcom b
            WHERE a.cli = b.cli AND a.typ='T' AND trim(b.cha) IN ('201006','201008','201009','201011','202005','202007', '201016', '21020')";


            $oracle_stmt = oci_parse($oracle_conn, $oracle_query);
            oci_execute($oracle_stmt);

            while ($row = oci_fetch_array($oracle_stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                $this->insertRowIntoCorporateTable($row);
            }

            oci_free_statement($oracle_stmt);
            oci_close($oracle_conn);
        } catch (PDOException $e) {
            $this->error('PDO Exception: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }


    public function update_pep()
    {
        try {
            $host = '172.19.2.86';
            $user = 'tajbank';
            $pwd = 'Tajbank123_';
            $port = '1522';
            $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
            $oracle_conn = oci_connect($user, $pwd, "172.19.2.86:$port/xepdb1");

            $oracle_query =  " SELECT
            trim(a.age) branch_code,trim(a.cli) customer_id,
            trim(initcap(a.nom)) customer_name,trim(c.ncp) account_number,c.sin balance,
            c.dbt debited,c.crt credited,c.dou date_opened, a.ges account_officer_id
            from tajprod_bkcli a,
            tajprod_bkicli b, tajprod_bkcom c
            where a.cli = b.cli and a.cli = c.cli and b.cli = c.cli
            and ((b.iden='0000000014' and b.vala='O') or (b.iden='0000000015' and b.vala='O'))";


            $oracle_stmt = oci_parse($oracle_conn, $oracle_query);
            oci_execute($oracle_stmt);

            while ($row = oci_fetch_array($oracle_stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                $this->insertRowIntoPepTable($row);
            }

            oci_free_statement($oracle_stmt);
            oci_close($oracle_conn);
        } catch (PDOException $e) {
            $this->error('PDO Exception: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }


    private function insertRowIntoCorporateTable($row)
    {
        try {
            $host = "172.19.2.86";
            $port = "5432";
            $dbname = "tajbank";
            $username = "postgres";
            $password = "Tajbank123_";

            $pgsqlConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);

            $pgsqlConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pgsqlConn->prepare("
            INSERT INTO corporate_accounts (
                CUSTOMER_ID,
                BRANCH,
                DIRECTORID,
                FIRSTNAME,
                LASTNAME,
                ACCOUNTNUMBER,
                BVN,
                COMPANYNAME,
                CREDITED,
                DEBITED,
                BALANCE,
                TYPE,
                GL_CODE,
                DATE_OPENED
            ) VALUES (
                :CUSTOMER_ID,
                :BRANCH,
                :DIRECTORID,
                :FIRSTNAME,
                :LASTNAME,
                :ACCOUNTNUMBER,
                :BVN,
                :COMPANYNAME,
                :CREDITED,
                :DEBITED,
                :BALANCE,
                :TYPE,
                :GL_CODE,
                :DATE_OPENED
            )            
            ");

            $stmt->bindParam(':CUSTOMER_ID', $row['CUSTOMER_ID']);
            $stmt->bindParam(':BRANCH', $row['BRANCH']);
            $stmt->bindParam(':DIRECTORID', $row['DIRECTORID']);
            $stmt->bindParam(':FIRSTNAME', $row['FIRSTNAME']);
            $stmt->bindParam(':LASTNAME', $row['LASTNAME']);
            $stmt->bindParam(':ACCOUNTNUMBER', $row['ACCOUNTNUMBER']);
            $stmt->bindParam(':BVN', $row['BVN']);
            $stmt->bindParam(':COMPANYNAME', $row['COMPANYNAME']);
            $stmt->bindParam(':CREDITED', $row['CREDITED']);
            $stmt->bindParam(':DEBITED', $row['DEBITED']);
            $stmt->bindParam(':BALANCE', $row['BALANCE']);
            $stmt->bindParam(':TYPE', $row['TYPE']);
            $stmt->bindParam(':GL_CODE', $row['GL_CODE']);
            $stmt->bindParam(':DATE_OPENED', $row['DATE_OPENED']);

            $stmt->execute();

            $this->info('Row inserted into PostgreSQL table successfully!');
        } catch (PDOException $e) {
            $this->error('PDO Exception: ' . $e->getMessage());
        } finally {
            $pgsqlConn = null;
        }
    }




    public function update_customers()
    {
        try {
            $host = '172.19.20.60';
            $user = 'novaji';
            $pwd = 'novali123';
            $port = '1521';
            $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
            $oracle_conn = oci_connect($user, $pwd, "172.19.20.60:$port/tajrep");

            $oracle_query = "SELECT DISTINCT a.cli AS customer_id, a.nomrest AS account_name, b.ncp AS Account_number, b.cha AS gl_code, (SELECT c.valmt AS bvn FROM tajprod.bkicli c 
             WHERE a.cli = c.cli  AND c.valmt IS NOT NULL  AND ROWNUM = 1) AS bvn FROM 
            tajprod.bkcli a JOIN  tajprod.bkcom b ON a.cli = b.cli WHERE  b.cli = a.cli ";

            $oracle_stmt = oci_parse($oracle_conn, $oracle_query);
            oci_execute($oracle_stmt);

            while ($row = oci_fetch_array($oracle_stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                $this->insertRowIntoCustomersTable($row);
            }

            oci_free_statement($oracle_stmt);
            oci_close($oracle_conn);
        } catch (PDOException $e) {
            $this->error('PDO Exception: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }





    private function insertRowIntoCustomersTable($row)
    {
        try {
            $host = "172.19.2.86";
            $port = "5432";
            $dbname = "tajbank";
            $username = "postgres";
            $password = "Tajbank123_";

            $pgsqlConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);

            $pgsqlConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pgsqlConn->prepare("
            INSERT INTO all_customers (
                customer_id,
                account_name,
                account_number,
                gl_code,
                bvn
            ) VALUES (
                :CUSTOMER_ID,
                :ACCOUNT_NAME,
                :ACCOUNT_NUMBER,
                :GL_CODE,
                :BVN
            )                      
            ");

            $stmt->bindParam(':CUSTOMER_ID', $row['CUSTOMER_ID']);
            $stmt->bindParam(':ACCOUNT_NAME', $row['ACCOUNT_NAME']);
            $stmt->bindParam(':ACCOUNT_NUMBER', $row['ACCOUNT_NUMBER']);
            $stmt->bindParam(':GL_CODE', $row['GL_CODE']);
            $stmt->bindParam(':BVN', $row['BVN']);


            $stmt->execute();

            $this->info('Row inserted into PostgreSQL table successfully!');
        } catch (PDOException $e) {
            $this->error('PDO Exception: ' . $e->getMessage());
        } finally {
            $pgsqlConn = null;
        }
    }


    private function  insertRowIntoPepTable($row)
    {
        try {
            $host = "172.19.2.86";
            $port = "5432";
            $dbname = "tajbank";
            $username = "postgres";
            $password = "Tajbank123_";

            $pgsqlConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);

            $pgsqlConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pgsqlConn->prepare("
            INSERT INTO pep_accounts (
                branch_code,
                customer_id,
                customer_name,
                account_number,
                balance,
                debited,
                credited,
                date_opened,
                account_officer_id
            ) VALUES (
                :BRANCH_CODE,
                :CUSTOMER_ID,
                :CUSTOMER_NAME,
                :ACCOUNT_NUMBER,
                :BALANCE,
                :DEBITED,
                :CREDITED,
                :DATE_OPENED,
                :ACCOUNT_OFFICER_ID
            )                      
            ");

            $stmt->bindParam(':BRANCH_CODE', $row['BRANCH_CODE']);
            $stmt->bindParam(':CUSTOMER_ID', $row['CUSTOMER_ID']);
            $stmt->bindParam(':CUSTOMER_NAME', $row['CUSTOMER_NAME']);
            $stmt->bindParam(':ACCOUNT_NUMBER', $row['ACCOUNT_NUMBER']);
            $stmt->bindParam(':BALANCE', $row['BALANCE']);
            $stmt->bindParam(':DEBITED', $row['DEBITED']);
            $stmt->bindParam(':CREDITED', $row['CREDITED']);
            $stmt->bindParam(':DATE_OPENED', $row['DATE_OPENED']);
            $stmt->bindParam(':ACCOUNT_OFFICER_ID', $row['ACCOUNT_OFFICER_ID']);

            $stmt->execute();

            $this->info('Row inserted into PostgreSQL table successfully!');
        } catch (PDOException $e) {
            $this->error('PDO Exception: ' . $e->getMessage());
        } finally {
            $pgsqlConn = null;
        }
    }
}
