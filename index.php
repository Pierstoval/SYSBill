<?php

require __DIR__.'/vendor/autoload.php';

use Ovh\Api;
use Symfony\Component\BrowserKit\HttpBrowser;

date_default_timezone_set('Europe/Paris');

if (!file_exists(__DIR__.'/credentials.php')) {
    echo "You must create the \"credentials.php\" file before running this script.\n";
    echo "To do so, you can copy the \"credentials.php.dist\" file and replace the values with your own.";
}

[
    'username' => $soyoustartUsername,
    'password' => $soyoustartPassword,
    'application_key' => $appKey,
    'application_secret' => $appSecret,
] = require __DIR__.'/credentials.php';

$apiEndpoint = 'soyoustart-eu';
$ovh = new Api($appKey, $appSecret, $apiEndpoint);





// Get credentials.
// The API endpoint allows to retrieve a "consumerKey" and a "validationUrl",
// which we will need for further authentication on the API.
$credentials = $ovh->requestCredentials([
    [
        'method' => 'GET',
        'path' => '/*',
    ],
    [
        'method' => 'POST',
        'path' => '/auth',
    ],
]);





// Now, take the "validationUrl", and automatically submit the authentication form.
// This is the place where 2FA will break the workflow:
// If you have 2FA, then the browser will fail here because it has to wait for your input,
// like an e-mail or an SMS, and this is impossible to automatize here.
$browser = new HttpBrowser();
$crawler = $browser->request('GET', $credentials['validationUrl']);
$emailFieldName = $crawler->filter('input[placeholder="Email"]')->attr('name');
$passwordFieldName = $crawler->filter('input[placeholder="Password"]')->attr('name');
$form = $crawler->filter('form[method="POST"]')->form();
$values = $form->getValues();
$values[$emailFieldName] = $soyoustartUsername;
$values[$passwordFieldName] = $soyoustartPassword;
$form->setValues($values);
$crawler = $browser->submit($form);
if (200 !== $browser->getResponse()->getStatusCode()) {
    echo "Non-200 status code after auth validation...\n";
    exit(1);
}



// Now fetch the list of bills of the current month
$firstDayOfCurrentMonth = (new DateTimeImmutable())->setDate(date('Y'), date('m'), 1)->setTime(0, 0);
$lastDayOfCurrentMonth = (new DateTimeImmutable())->setDate(date('Y'), date('m'), date('t'))->setTime(23, 59, 59);
$result = $ovh->get('/me/bill', array(
    'date.from' => $firstDayOfCurrentMonth->format(DATE_ATOM),
    'date.to' => $lastDayOfCurrentMonth->format(DATE_ATOM),
));
if (!isset($result[0])) {
    echo "No bill to process.\n";
    exit(1);
}
$billId = $result[0];




// Download bill's metadata
$bill = $ovh->get("/me/bill/$billId");
// Download bill's PDF
$pdf = file_get_contents($bill['pdfUrl']);
// And save it to file :)
file_put_contents("soyoustart_$billId.pdf", $pdf);
