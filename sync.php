<?php

require_once('vendor/autoload.php');

$everyaction_un = '';
$everyaction_pw = '';

// Spoke database settings
$db_host = '';
$db_name = '';
$db_password = '';

// Create Guzzle HTTP client for EveryAction API
use GuzzleHttp\Client;
$client = new Client([
    'base_uri' => 'https://api.securevan.com',
    'timeout'  => 2.0,
]);

// Open database connection to Spoke
$db_connection = @pg_connect("host=$db_host dbname=$db_name password=$db_password");

// If database connection fails, send email to data team
if (!$db_connection) {
    throw new Exception("Failed to connect to database.");
    echo "Please update the Spoke database credentials to ensure future opt-outs successfully sync from Spoke to EveryAction.";
    exit();
}

// SELECT recent opt-outs
$result = pg_query($db_connection, "SELECT cell,created_at FROM opt_out WHERE created_at > CURRENT_DATE - interval '7 days' ORDER BY id DESC");
echo 'Total opt-outs: '.pg_num_rows($result).'<br />';
$countVanId = 0;
while ($optout = pg_fetch_assoc($result)) {
    $result2 = pg_query($db_connection, "SELECT external_id FROM campaign_contact WHERE cell='{$optout['cell']}' ORDER BY id DESC LIMIT 1"); // Get VanID of opt-outs
    $cellFormatted = str_replace('+','',$optout['cell']); // Remove + from phone number
    while ($campaign = pg_fetch_object($result2)) {
        $datetime = new DateTime($optout['created_at']);

        $datetime = $datetime->format(DateTime::ATOM);
        
        $new_contact_details = array(
            'Cell Number'           => ltrim($cellFormatted,'1'),
            'Spoke Opt-Out Date'    => $datetime,
        );
        if ($campaign->external_id) { // If opt-out has VanID
            $countVanId++;
            
            // Compile data needed for EveryAction API call from Spoke data
            $data = new stdClass();
            $phoneInput = new stdClass();
            $phoneInput->phoneNumber = $cellFormatted;
            $phoneInput->phoneOptInStatus = 'O';
            $data->phones[] = $phoneInput;
            
            // EveryAction API call
            $response = $client->request('POST', '/v4/people/'.$campaign->external_id, [
                'json' => $data,
                'auth' => [$everyaction_un, $everyaction_pw],
                'http_errors' => false
            ]);
            $new_contact_details['VanID'] = (int)$campaign->external_id;
        }
        $to = sprintf("(%s) %s-%s",
                      substr($cellFormatted, 1, 3),
                      substr($cellFormatted, 4, 3),
                      substr($cellFormatted, 7));
    }
    pg_free_result($result2);
}
echo 'Total opt-outs synced: '.$countVanId;

pg_free_result($result);
pg_close($db_connection);
