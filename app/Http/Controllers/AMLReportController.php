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
    public function insert_xml($xml, $userid, $typeid, $filename, $no_of_records)
    {
        $pdo = new PDO('pgsql:host=127.0.0.1;dbname=tajbank', 'postgres', 'Tajbank123_');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "INSERT INTO AML_REPORT (NAME, USER_ID, REPORT_TYPE_ID, XML_DATA, STATUS, NUMBER_OF_RECORDS)
                VALUES (:filename, :userid, :typeid, :xml_data, 1, :no_of_records)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':userid', $userid);
        $stmt->bindParam(':typeid', $typeid);
        $stmt->bindParam(':xml_data', $xml);
        $stmt->bindParam(':no_of_records', $no_of_records);
        $stmt->execute();
    }

    // Function to generate XML for each transaction
   private function generate_inflow_transaction_xml($row)
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

    if($row['tran_type'] == 'INWARD_BUFFER'){
    // Add source account information
    $source_account = $from->addChild('from_account');
    $source_account->addChild('institution_name', htmlspecialchars(trim($row['t_source_institution_name']) ?: '     '));
    $source_account->addChild('institution_code', htmlspecialchars($row['t_source_institution_code'] ?: '     '));
    $source_account->addChild('non_bank_institution', 'false');
    $source_account->addChild('account', htmlspecialchars($row['t_source_account_number'] ?: '     '));
    
    $source_account->addChild('currency_code', htmlspecialchars($this->getCurrencyName($row['t_source_currency_code'] ?: '566')));

    
    $source_account->addChild('account_name', htmlspecialchars(trim($row['t_source_account_name']) ?: '     '));

    // Add source country
    $from->addChild('from_country', htmlspecialchars(trim($row['t_source_country']) ?: 'NG'));
    
    }
    
    if (in_array($row['tran_type'], ['LOCAL_DEPOSIT', 'FOREIGN_DEPOSIT', 'FX_INWARD', 'INWARD_BUFFER'])) {
    $from_person = $from->addChild('from_person');
    $from_person->addChild('gender', htmlspecialchars(trim($row['t_gender']) ?: 'M'));
    $from_person->addChild('first_name', htmlspecialchars(trim($row['t_firstname']) ?: '     '));
    $from_person->addChild('last_name', htmlspecialchars(trim($row['t_lastname']) ?: '     '));

    // Add source country for all relevant transaction types
    $from->addChild('from_country', htmlspecialchars(trim($row['t_source_country']) ?: 'NG'));
    }



    // To my client section
    $to_client = $xml->addChild('t_to_my_client');
    $to_client->addChild('to_funds_code', htmlspecialchars($row['t_dest_funds_code'] ?: 'L'));
    
    if (in_array($row['tran_type'], ['FOREIGN_DEPOSIT', 'FX_INWARD'])) {
    // Add to_foreign_currency for foreign currency transactions
    $to_foreign_currency = $to_client->addChild('to_foreign_currency');
    $to_foreign_currency->addChild('foreign_currency_code',  htmlspecialchars($this->getCurrencyName($row['t_source_currency_code'] ?: '840')));
    $to_foreign_currency->addChild('foreign_amount', htmlspecialchars(trim($row['t_dest_foreign_amount']) ?: 0));
    $to_foreign_currency->addChild('foreign_exchange_rate', htmlspecialchars(trim($row['t_dest_exchange_rate']) ?: 1));
    }
    

    // Add destination account information
    $to_account = $to_client->addChild('to_account');
    $to_account->addChild('institution_name', htmlspecialchars(trim($row['t_dest_institution_name']) ?: '     '));
    $to_account->addChild('institution_code', htmlspecialchars(trim($row['t_dest_institution_code']) ?: '     '));
    $to_account->addChild('non_bank_institution', 'true');
    $to_account->addChild('branch', htmlspecialchars($row['branch_name'] ?: '     '));
    $to_account->addChild('account', htmlspecialchars($row['t_dest_account_number'] ?: '     '));
    
    $to_account->addChild('currency_code', htmlspecialchars($this->getCurrencyName($row['t_dest_currency_code'] ?: '566')));
    
    $to_account->addChild('account_name', htmlspecialchars(trim($row['t_dest_account_name']) ?: '     '));
    
    $to_account->addChild('client_number', htmlspecialchars($row['t_client_number'] ?: '22222222222'));
    
    $to_account->addChild('personal_account_type', 'E');

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
    
    $signatory->addChild('role', 'D');

    // Account opened date
    $to_account->addChild('opened', date('Y-m-d\TH:i:s', strtotime($row['t_acctopndate'] ?: 'now')));

    // Balance and status
    $to_account->addChild('balance', htmlspecialchars($row['t_balance'] ?: 0));
    $to_account->addChild('status_code', 'A');
    
    // Beneficiary
    $to_account->addChild('beneficiary', substr(htmlspecialchars(trim($row['t_dest_account_number'])), 0, 50) ?: '     ');

    // Add destination country
    $to_client->addChild('to_country', htmlspecialchars(trim($row['t_dest_country']) ?: 'NG'));
    
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml->asXML());

    // Format the XML for readability (without adding a new XML declaration)
    $dom->formatOutput = true;

    // Get the XML string without the declaration
    $xmlString = $dom->saveXML($dom->documentElement);  // This avoids including the declaration

    // Return the formatted XML string
    return $xmlString;
}





// Function to generate XML for each outward transaction
private function generate_outward_transaction_xml($row)
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

    // From section (destination account)
    $from = $xml->addChild('t_from_my_client');
    $from->addChild('from_funds_code', htmlspecialchars($row['t_source_funds_code'] ?: 'L'));

    // Add source account information
    $source_account = $from->addChild('from_account');
    $source_account->addChild('institution_name', htmlspecialchars(trim($row['t_source_institution_name']) ?: '     '));
    $source_account->addChild('institution_code', htmlspecialchars($row['t_source_institution_code'] ?: '     '));
    $source_account->addChild('non_bank_institution', 'true');
    $source_account->addChild('branch', htmlspecialchars($row['branch_name'] ?: '     '));
    $source_account->addChild('account', htmlspecialchars($row['t_source_account_number'] ?: '     '));
    $source_account->addChild('currency_code', htmlspecialchars($this->getCurrencyName($row['t_source_currency_code'] ?: '566')));
    $source_account->addChild('account_name', htmlspecialchars(trim($row['t_source_account_name']) ?: '     '));

    // Add client number and account type
    $source_account->addChild('client_number', htmlspecialchars($row['t_client_number'] ?: '11111111111'));
    $source_account->addChild('personal_account_type', 'E');

    // Add signatory information (person making the withdrawal)
    $signatory = $source_account->addChild('signatory');
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
    
    $signatory->addChild('role', 'D');

    // Account opened date
    $source_account->addChild('opened', date('Y-m-d\TH:i:s', strtotime($row['t_acctopndate'] ?: 'now')));

    // Balance and status
    $source_account->addChild('balance', htmlspecialchars($row['t_balance'] ?: 0));
    $source_account->addChild('status_code', 'A');
    
    // Beneficiary
    $source_account->addChild('beneficiary', substr(htmlspecialchars(trim($row['t_source_account_name'])), 0, 50) ?: '     ');

    // Add destination country
    $from->addChild('from_country', htmlspecialchars(trim($row['t_source_country']) ?: 'NG'));

    // To section (source account, i.e., recipient)
    $to = $xml->addChild('t_to');
    $to->addChild('to_funds_code', htmlspecialchars($row['t_dest_funds_code'] ?: 'L'));

    // Add recipient personal information
    $to_person = $to->addChild('to_person');
    $to_person->addChild('gender', htmlspecialchars(trim($row['t_gender']) ?: 'M'));
    $to_person->addChild('first_name', htmlspecialchars(trim($row['t_firstname']) ?: '     '));
    $to_person->addChild('last_name', htmlspecialchars(trim($row['t_lastname']) ?: '     '));

    // Add destination country
    $to->addChild('to_country', htmlspecialchars($row['t_dest_country'] ?: 'NG'));

    // Return the formatted XML string
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
        
    if (in_array(strtoupper($tran_type), ['OUTWARD_BUFFER', 'LOCAL_WITHDRAWAL', 'FOREIGN_WITHDRAWAL'])) {
         $xml = $this->generate_outward_transaction_xml($row, $tran_type);
    }
    // For inward or deposit transactions
    elseif (in_array(strtoupper($tran_type), ['INWARD_BUFFER  ', 'LOCAL_DEPOSIT', 'FOREIGN_DEPOSIT', 'INWARD_BUFFER', 'FX_INWARD'])) {
         $xml = $this->generate_inward_transaction_xml($row, $tran_type);
    }
    // Handle unsupported transaction types
    else {
        throw new Exception("Unsupported transaction type: " . $tran_type);
    }
    
       // $xml = $this->generate_inflow_transaction_xml($row, $tran_type);
        $xml_container .= $xml;
        $count++;
        
             

        if ($count % 500 == 0) {
            // Insert data after every 500 records

            // Adjust filename based on transaction type
            $filename = 'TAJ_' . 'CTR' . '_' . date('Y-m-d') . '_' . rand(9999, 99999999) . '_' . strtoupper($tran_type) . '_' . ceil($count / 500);
            
            $no_of_records = 500;
            
            $full_xml = $this->xml_header($filename) . $xml_container . $this->xml_footer();
            $this->insert_xml($full_xml, $userid, $typeid, $filename, $no_of_records);

            // Reset the container after insertion
            $xml_container = '';
        }
    }

    // Insert remaining data if any
    if (!empty($xml_container)) {
    
        $no_of_records = $count - 500;
        
        $filename = 'TAJ_' . 'CTR' . '_' . date('Y-m-d') . '_' . rand(9999, 99999999) . '_' . strtoupper($tran_type) . '_' . ceil($count / 500);
        $full_xml = $this->xml_header($filename) . $xml_container . $this->xml_footer();
        $this->insert_xml($full_xml, $userid, $typeid, $filename, $no_of_records);
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

public function getCurrencyName($currency_code) {
    $currency_map = [
        '840' => 'USD',
        '978' => 'EUR',
        '826' => 'GBP',
        '392' => 'JPY',
        '156' => 'CNY',
        '566' => 'NGN'
    ];

    // Return the currency name, or 'NGN' if not found
    return isset($currency_map[$currency_code]) ? $currency_map[$currency_code] : 'NGN';
}
}