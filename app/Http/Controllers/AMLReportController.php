<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;
use SimpleXMLElement;



class AMLReportController extends BaseController
{
    // Function to generate XML header
    private function xml_header($filename)
    {
        $rent_id = rand(9999, 99999999);
        $date = date('Y-m-d\TH:i:s');

        return '<?xml version="1.0" standalone="yes"?>
        <report>
            <rentity_id>' . $rent_id . '</rentity_id>
            <submission_code>E</submission_code>
            <report_code>CTR</report_code>
            <entity_reference>' . $filename . '</entity_reference>
            <submission_date>' . $date . '</submission_date>
            <currency_code_local>NGN</currency_code_local>
            <reporting_person>
                <gender>M</gender>
                <title>MR</title>
                <first_name>SAHEED</first_name>
                <last_name>EKEOLERE</last_name>
                <birthdate>1975-09-15T12:00:00</birthdate>
                <passport_number>A09582023</passport_number>
                <passport_country>NG</passport_country>
                <nationality1>NG</nationality1>
                <phones>
                    <phone>
                        <tph_contact_type>P</tph_contact_type>
                        <tph_communication_type>M</tph_communication_type>
                        <tph_country_prefix>+234</tph_country_prefix>
                        <tph_number>8034261719</tph_number>
                    </phone>
                </phones>
                <addresses>
                    <address>
                        <address_type>P</address_type>
                        <address>7 ORAN STREET FLAT 8 WUSE ZONE 1 ABUJA</address>
                        <city>ABUJA</city>
                        <country_code>NG</country_code>
                        <state>ABUJA</state>
                    </address>
                </addresses>
                <email>saheed.ekeolere@tajbank.com</email>
                <occupation>Banker</occupation>
                <identification>
                    <type>C</type>
                    <number>A09582023</number>
                    <issue_date>2010-01-01T00:00:00</issue_date>
                    <issue_country>NG</issue_country>
                </identification>
            </reporting_person>
            <location>
                <address_type>B</address_type>
                <address>Plot 183 Moshood Olugbani St, Victoria Island 106104, Lagos.</address>
                <city>Lagos</city>
                <country_code>NG</country_code>
                <state>Lagos</state>
            </location>';
    }

    // Function to generate XML footer
    private function xml_footer()
    {
        return '<report_indicators><indicator>THRESHOLDREPORT</indicator></report_indicators></report>';
    }

    // Function to insert XML into the database
    public function insert_xml($xml, $userid, $typeid, $filename, $count)
    {
        $pdo = new PDO('pgsql:host=127.0.0.1;dbname=tajbank', 'postgres', 'Tajbank123_');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "INSERT INTO AML_REPORT (NAME, USER_ID, REPORT_TYPE_ID, XML_DATA, STATUS, NUMBER_OF_RECORDS)
                VALUES (:filename, :userid, :typeid, :xml_data, 1, :count)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':userid', $userid);
        $stmt->bindParam(':typeid', $typeid);
        $stmt->bindParam(':xml_data', $xml);
         $stmt->bindParam(':count', $count);
        $stmt->execute();
    }

    // Function to generate XML for each transaction
   private function generate_transaction_xml($row)
{
    // Create the root <transaction> element
    $xml = new SimpleXMLElement('<transaction/>');

    // Add the transaction fields, escaping special characters
    $xml->addChild('transactionnumber', htmlspecialchars($row['t_trans_number'] ?: '     '));
    $xml->addChild('internal_ref_number', htmlspecialchars($row['t_trans_number'] ?: '     '));
    $xml->addChild('transaction_location', htmlspecialchars(trim($row['t_location']) ?: '     '));
    $xml->addChild('transaction_description', htmlspecialchars(trim($row['transaction_description']) ?: '     '));

    // Handle date formatting for "date_transaction"
    $xml->addChild('date_transaction', date('Y-m-d\TH:i:s', strtotime($row['t_date'] ?: 'now')));

    // Add teller and authorized information
    $xml->addChild('teller', htmlspecialchars(trim($row['t_teller']) ?: 'SYSTEM'));
    $xml->addChild('authorized', htmlspecialchars(trim($row['t_authorized']) ?: 'SYSTEM'));

    // Add late deposit
    $xml->addChild('late_deposit', htmlspecialchars($row['t_late_deposit'] ?: 0));

    // Handle date formatting for "value_date"
    $xml->addChild('value_date', date('Y-m-d\TH:i:s', strtotime($row['t_value_date'] ?: 'now')));

    // Add transaction mode code
    $xml->addChild('transmode_code', htmlspecialchars($row['t_transmode_code'] ?: 'A'));

    // Add amount in local currency
    $xml->addChild('amount_local', htmlspecialchars($row['t_amount_local'] ?: 0));

    // From section
    $from = $xml->addChild('t_from');
    $from->addChild('from_funds_code', htmlspecialchars($row['t_source_funds_code'] ?: 'L'));

    // Add source account information
    $source_account = $from->addChild('from_account');
    $source_account->addChild('institution_name', htmlspecialchars(trim($row['t_source_institution_name']) ?: '     '));
    $source_account->addChild('institution_code', htmlspecialchars($row['t_source_institution_code'] ?: '     '));
    $source_account->addChild('non_bank_institution', 'false');
    $source_account->addChild('account', htmlspecialchars($row['t_source_account_number'] ?: '     '));
    $source_account->addChild('currency_code', htmlspecialchars($row['t_source_currency_code'] ?: 'NGN'));
    $source_account->addChild('account_name', htmlspecialchars(trim($row['t_source_account_name']) ?: '     '));

    // Add source country
    $from->addChild('from_country', htmlspecialchars(trim($row['t_source_country']) ?: 'NG'));

    // To my client section
    $to_client = $xml->addChild('t_to_my_client');
    $to_client->addChild('to_funds_code', htmlspecialchars($row['t_dest_funds_code'] ?: 'L'));

    // Add destination account information
    $to_account = $to_client->addChild('to_account');
    $to_account->addChild('institution_name', htmlspecialchars(trim($row['t_dest_institution_name']) ?: '     '));
    $to_account->addChild('institution_code', htmlspecialchars(trim($row['t_dest_institution_code']) ?: '     '));
    $to_account->addChild('non_bank_institution', 'true');
    $to_account->addChild('branch', htmlspecialchars($row['t_dest_institution_code'] ?: '     '));
    $to_account->addChild('account', htmlspecialchars($row['t_dest_account_number'] ?: '     '));
    $to_account->addChild('currency_code', htmlspecialchars($row['t_dest_currency_code'] ?: 'NGN'));
    $to_account->addChild('account_name', htmlspecialchars(trim($row['t_dest_account_name']) ?: '     '));

    // Add signatory information
    $signatory = $to_account->addChild('signatory');
    $signatory->addChild('is_primary', '1');

    $person = $signatory->addChild('t_person');
    $person->addChild('gender', htmlspecialchars(trim($row['t_gender']) ?: 'M'));
    $person->addChild('title', htmlspecialchars(trim($row['t_title']) ?: '     '));
    $person->addChild('first_name', htmlspecialchars(trim($row['t_firstname']) ?: '     '));
    $person->addChild('last_name', htmlspecialchars(trim($row['t_lastname']) ?: '     '));

    // Handle date formatting for "birthdate"
    $person->addChild('birthdate', date('Y-m-d\TH:i:s', strtotime($row['t_dob'] ?: 'now')));

    $person->addChild('nationality1', 'NG');
    $person->addChild('residence', 'NG');

    // Phones
    $phones = $person->addChild('phones');
    $phone = $phones->addChild('phone');
    $phone->addChild('tph_contact_type', 'P');
    $phone->addChild('tph_communication_type', 'M');
    $phone->addChild('tph_country_prefix', '+234');
    $phone->addChild('tph_number', htmlspecialchars($row['t_phone'] ?: '     '));

    // Addresses
    $addresses = $person->addChild('addresses');
    $address = $addresses->addChild('address');
    $address->addChild('address_type', 'P');
    $address->addChild('address', htmlspecialchars(trim($row['t_address']) ?: '     '));
    $address->addChild('city', htmlspecialchars(trim($row['t_city']) ?: '     '));
    $address->addChild('country_code', 'NG');
    $address->addChild('state', htmlspecialchars(trim($row['t_state']) ?: '     '));

    // Occupation
    $person->addChild('occupation', 'Business');

    // Identification information
    $identification = $person->addChild('identification');
    $identification->addChild('type', 'B');
    $identification->addChild('number', htmlspecialchars($row['t_idnumber'] ?: '     '));

    // Handle date formatting for "issue_date"
    $identification->addChild('issue_date', date('Y-m-d\TH:i:s', strtotime($row['t_idregdate'] ?: 'now')));

    $identification->addChild('issue_country', 'NG');

    // Tax information
    $person->addChild('tax_number', htmlspecialchars($row['t_taxno']));
    $person->addChild('tax_reg_number', htmlspecialchars($row['t_taxregdate']));
    $person->addChild('source_of_wealth', 'Business');

    // Account opened date
    $to_account->addChild('opened', date('Y-m-d\TH:i:s', strtotime($row['t_acctopndate'] ?: 'now')));

    // Balance and status
    $to_account->addChild('balance', htmlspecialchars($row['t_balance'] ?: 0));
    $to_account->addChild('status_code', 'A');
    
    // Beneficiary
    $to_account->addChild('beneficiary', substr(htmlspecialchars(trim($row['t_dest_account_number'])), 0, 50) ?: '     ');

    // Add destination country
    $to_client->addChild('to_country', htmlspecialchars(trim($row['t_dest_country']) ?: 'NG'));

    return $xml->asXML();
}


    // Function to handle inward data processing
public function generateTransactionReport(Request $request)
{
    $start_date = $request->input('start_date'); 
    $end_date = $request->input('end_date');
    $userid = $request->input('userid');
    $typeid = $request->input('typeid');
    $tran_type = $request->input('tran_type'); // Accept transaction type as input
    
    if (empty($start_date) || empty($end_date) || empty($userid) || empty($typeid) || empty($tran_type)) {
        return response()->json(['error' => 'Missing required parameters'], 400);
    }
    
    $pdo = new PDO('pgsql:host=127.0.0.1;dbname=tajbank', 'postgres', 'Tajbank123_');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $xml_container = '';
    $count = 0;

    // Modify the query to filter by transaction type
    $stmt = $pdo->prepare("SELECT * FROM ctr_transactions WHERE tran_type = :tran_type AND TO_DATE(TO_CHAR(t_value_date, 'DD-MON-YYYY'), 'DD-MON-YYYY') BETWEEN :start_date AND :end_date");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':tran_type', $tran_type);  // Filter by transaction type
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Generate XML for each transaction
        $xml = $this->generate_transaction_xml($row, $tran_type);
        $xml_container .= $xml;
        $count++;

        if ($count % 500 == 0) {
            // Insert data after every 500 records

            // Adjust filename based on transaction type
            $filename = 'TAJ_' . 'CTR' . '_' . date('Y-m-d') . '_' . rand(9999, 99999999) . '_' . strtoupper($tran_type) . '_' . ceil($count / 500);
            $full_xml = $this->xml_header($filename) . $xml_container . $this->xml_footer();
            $this->insert_xml($full_xml, $userid, $typeid, $filename, $count);

            // Reset the container after insertion
            $xml_container = '';
        }
    }

    // Insert remaining data if any
    if (!empty($xml_container)) {
        $filename = 'TAJ_' . 'CTR' . '_' . date('Y-m-d') . '_' . rand(9999, 99999999) . '_' . strtoupper($tran_type) . '_' . ceil($count / 500);
        $full_xml = $this->xml_header($filename) . $xml_container . $this->xml_footer();
        $this->insert_xml($full_xml, $userid, $typeid, $filename, $count);
    }

    return response()->json(['message' => 'XML generated and saved to database']);
}
public function downloadXml(Request $request)
{
    // Get report_id from query parameter
    $report_id = $request->query('report_id');
    
    if (!$report_id) {
        return response()->json(['error' => 'Missing report_id parameter'], 400);
    }

    // Fetch the XML data and the filename from the database based on report_id
    $pdo = new PDO('pgsql:host=127.0.0.1;dbname=tajbank', 'postgres', 'Tajbank123_');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT NAME, XML_DATA FROM AML_REPORT WHERE ID = :report_id");
    $stmt->bindParam(':report_id', $report_id);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return response()->json(['error' => 'Report not found'], 404);
    }

    // Retrieve the XML data and the filename (NAME)
    $xml_data = $result['xml_data'];
    $filename = $result['name'];  // This is the name you want for the downloaded file

    // Return the XML data as a downloadable file with the proper filename
    return response($xml_data, 200)
        ->header('Content-Type', 'application/xml')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '.xml"');
}
}