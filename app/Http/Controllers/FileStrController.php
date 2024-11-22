<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;

class FileStrController extends BaseController
{
 
    
$host = "172.19.2.86";
$port = "5432";
$dbname = "tajbank";
$username = "postgres";
$password = "Tajbank123_";

try {
    // Connect to PostgreSQL
    $pgsqlConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);

    // Query to select one record from the PostgreSQL table
    $query = "SELECT * FROM str WHERE id = 2723826 LIMIT 1";

    // Prepare and execute the query
    $stmt = $pgsqlConn->prepare($query);
    $stmt->execute();

    // Fetch the result (only one row)
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Use the fetched data to replace the placeholders in the XML template
    if ($row) {
        $transactionXml = "<transaction>
                            <transactionnumber>{$row['t_trans_number']}</transactionnumber>
                            <internal_ref_number>{$row['t_trans_number']}</internal_ref_number>
                            <transaction_location></transaction_location>
                            <transaction_description>{$row['transaction_description']}</transaction_description>
                            <date_transaction>{$row['t_value_date']}</date_transaction>
                            <teller>SYSTEM</teller>
                            <authorized>SYSTEM</authorized>
                            <late_deposit>0</late_deposit>
                            <value_date>{$row['t_value_date']}</value_date>
                            <transmode_code>C</transmode_code>
                            <amount_local>{$row['t_amount_local']}</amount_local>
                            <t_from>
                                <from_funds_code>B</from_funds_code>
                                <from_account>
                                    <institution_name>{$row['t_trans_number']}</institution_name>
                                    <institution_code>{$row['t_source_institution_code']}</institution_code>
                                    <non_bank_institution>true</non_bank_institution>
                                    <account>{$row['t_source_account_number']}</account>
                                    <currency_code></currency_code>
                                    <account_name>{$row['t_source_account_name']}</account_name>
                                </from_account>
                                <from_country>NG</from_country>
                            </t_from>
                            <t_to_my_client>
                                <to_funds_code>B</to_funds_code>
                                <to_account>
                                    <institution_name>Taj Bank</institution_name>
                                    <swift>TAJJNGLA</swift>
                                    <non_bank_institution>false</non_bank_institution>
                                    <branch></branch>
                                    <account>{$row['t_dest_account_number']}</account>
                                    <currency_code></currency_code>
                                    <account_name>{$row['t_dest_account_name']}</account_name>
                                    <client_number>{$row['t_client_number']}</client_number>
                                    <personal_account_type>E</personal_account_type>
                                    <signatory>
                                        <is_primary>1</is_primary>
                                        <t_person>
                                            <gender></gender>
                                            <title></title>
                                            <first_name>{$row['t_source_person_first_name']}</first_name>
                                            <last_name>{$row['t_source_person_last_name']}</last_name>
                                            <birthdate>{$row['DOB']}</birthdate>
                                            <nationality1>NG</nationality1>
                                            <residence>NG</residence>
                                            <phones>
                                                <phone>
                                                    <tph_contact_type>P</tph_contact_type>
                                                    <tph_communication_type>M</tph_communication_type>
                                                    <tph_country_prefix>+234</tph_country_prefix>
                                                    <tph_number></tph_number>
                                                </phone>
                                            </phones>
                                            <addresses>
                                                <address>
                                                    <address_type>P</address_type>
                                                    <address></address>
                                                    <city></city>
                                                    <country_code>NG</country_code>
                                                    <state></state>
                                                </address>
                                            </addresses>
                                            <occupation>Business</occupation>
                                            <identification>
                                                <type>B</type>
                                                <number></number>
                                                <issue_date>{$row['COMPANY_REG_DATE']}</issue_date>
                                                <issue_country>NG</issue_country>
                                            </identification>
                                            <tax_number></tax_number>
                                            <tax_reg_number></tax_reg_number>
                                            <source_of_wealth>Business</source_of_wealth>
                                        </t_person>
                                        <role>D</role>
                                    </signatory>
                                    <opened>{$row['REG_DATE']}</opened>
                                    <balance></balance>
                                    <status_code>A</status_code>
                                    <beneficiary></beneficiary>
                                </to_account>
                                <to_country>NG</to_country>
                            </t_to_my_client>
                        </transaction>";

        // Do something with $transactionXml, like printing it or saving it to a file
        echo $transactionXml . "\n";
    } else {
        echo "No records found.\n";
    }
} catch (PDOException $e) {
    die("Error: Could not connect. " . $e->getMessage());
}
  
}