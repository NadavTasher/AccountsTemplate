<?php
const DATABASE = "../files/accounts/database.json";
$database = json_decode(file_get_contents(DATABASE));
$result = new stdClass();
main();
echo json_encode($result);

function main()
{
    if (isset($_POST["action"])) {
        $action = $_POST["action"];
        switch ($action) {
            case "login":
                login();
                break;
            case "register":
                register();
                break;
            case "verify":
                verify();
                break;
        }
    }
}

function filter($source)
{
    // Filter inputs from XSS and other attacks
    $source = str_replace("<", "", $source);
    $source = str_replace(">", "", $source);
    return $source;
}

function generateRandom($length)
{
    $current = str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ")[0];
    if ($length > 0) {
        return $current . generateRandom($length - 1);
    }
    return "";
}

function login()
{
    global $result, $database;
    function generateCertificate()
    {
        global $database;
        $random = generateRandom(28);
        foreach ($database->accounts as $account) {
            foreach ($account->certificates as $certificate) {
                if ($certificate === $random) return generateCertificate();
            }
        }
        return $random;
    }

    foreach ($database->accounts as $account) {

    }
}

function register()
{
    global $result, $database;


    function generateSalt()
    {
        return generateRandom(32);
    }

}

function save()
{
    global $database;
    file_put_contents(json_encode($database), DATABASE);
}