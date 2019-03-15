<?php
const DATABASE = "../files/accounts/database.json";
$database = json_decode(file_get_contents(DATABASE));
$result = new stdClass();
$result->errors = new stdClass();
main();
echo json_encode($result);

function main()
{
    global $result;
    if (isset($_POST["action"])) {
        $action = $_POST["action"];
        if (isset($_POST[$action])) {
            $parameters = json_decode($_POST[$action]);
            switch ($action) {
                case "login":
                    if (isset($parameters->name) && isset($parameters->password))
                        login($parameters->name, $parameters->password);
                    else
                        $result->errors->login = "Missing info";
                    break;
                case "register":
                    if (isset($parameters->name) && isset($parameters->password))
                        register($parameters->name, $parameters->password);
                    else
                        $result->errors->registration = "Missing info";
                    break;
                case "verify":
                    if (isset($parameters->certificate))
                        verify($parameters->certificate);
                    else
                        $result->errors->verification = "Missing info";
                    break;
            }
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

function verify($certificate)
{
    global $result, $database;
    foreach ($database->accounts as $account) {
        foreach ($account->certificates as $current) {
            if ($current === $certificate) {
                $result->verify = new stdClass();
                $result->verify->name = $account->name;
                return true;
            }
        }
    }
    return false;
}

function login($name, $password)
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

    function verifyPassword($name, $password)
    {
        global $database;
        foreach ($database->accounts as $account) {
            if ($account->name === $name) {
                return md5(sha1($account->saltA . $password . $account->saltB)) === $account->hashed;
            }
        }
        return false;
    }

    $accountFound = false;
    foreach ($database->accounts as $account) {
        if ($account->name === $name) {
            $accountFound = true;
            if (verifyPassword($name, $password)) {
                $certificate = generateCertificate();
                array_push($account->certificates, $certificate);
                save();
                $result->login = new stdClass();
                $result->login->certificate = $certificate;
            } else {
                $result->errors->login = "Incorrect password";
            }
        }
    }
    if (!$accountFound)
        $result->errors->login = "Account not found";
}

function register($name, $password)
{
    global $result, $database;

    function generateID()
    {
        global $database;
        $random = generateRandom(10);
        foreach ($database->accounts as $account) {
            if ($account->id === $random) return generateID();
        }
        return $random;
    }

    function generateSalt()
    {
        return generateRandom(32);
    }

    function checkName($name)
    {
        global $database;
        foreach ($database->accounts as $account) {
            if ($account->name === $name) return false;
        }
        return true;
    }

    $result->register = new stdClass();
    $result->register->success = false;
    if (checkName($name)) {
        $account = new stdClass();
        $account->id = generateID();
        $account->name = $name;
        $account->certificates = array();
        $account->saltA = generateSalt();
        $account->saltB = generateSalt();
        $account->hashed = md5(sha1($account->saltA . $password . $account->saltB));
        array_push($database->accounts, $account);
        save();
        $result->register = new stdClass();
        $result->register->success = true;
    } else {
        $result->errors->registration = "Name already taken";
    }
}

function save()
{
    global $database;
    file_put_contents(DATABASE, json_encode($database));
}