<?php
const MINIMUM_PASSWORD_LENGTH = 8;

define("DATABASE", dirname(__FILE__) . "/../../files/accounts/database.json");
$database = json_decode(file_get_contents(DATABASE));

$result = new stdClass();
$result->errors = new stdClass();

function init()
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
                        $result->errors->login = "Missing information";
                    break;
                case "register":
                    if (isset($parameters->name) && isset($parameters->password))
                        register($parameters->name, $parameters->password);
                    else
                        $result->errors->registration = "Missing information";
                    break;
                case "verify":
                    if (isset($parameters->certificate))
                        return verify($parameters->certificate);
                    else
                        $result->errors->verification = "Missing information";
                    break;
            }
        }
    }
    return null;
}

function filter($source)
{
    // Filter inputs from XSS and other attacks
    $source = str_replace("<", "", $source);
    $source = str_replace(">", "", $source);
    return $source;
}

function random($length)
{
    $current = str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ")[0];
    if ($length > 0) {
        return $current . random($length - 1);
    }
    return "";
}

function user($userID)
{
    global $database;
    foreach ($database->accounts as $account) {
        if ($account->id === $userID) {
            $user = $account;
            unset($user->saltA);
            unset($user->saltB);
            unset($user->hashed);
            unset($user->certificates);
            return $user;
        }
    }
    return null;
}

function verify($certificate)
{
    global $result, $database;
    foreach ($database->accounts as $account) {
        foreach ($account->certificates as $current) {
            if ($current === $certificate) {
                $result->verify = new stdClass();
                $result->verify->name = $account->name;
                return user($account->id);
            }
        }
    }
    return null;
}

function login($name, $password)
{
    global $result, $database;

    function certificate()
    {
        global $database;
        $random = random(64);
        foreach ($database->accounts as $account) {
            foreach ($account->certificates as $certificate) {
                if ($certificate === $random) return certificate();
            }
        }
        return $random;
    }

    function password($name, $password)
    {
        global $database;
        foreach ($database->accounts as $account) {
            if ($account->name === $name) {
                return hash("sha256", $account->saltA . $password . $account->saltB) === $account->hashed;
            }
        }
        return false;
    }

    $accountFound = false;
    foreach ($database->accounts as $account) {
        if ($account->name === $name) {
            $accountFound = true;
            if (password($name, $password)) {
                $certificate = certificate();
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

    function id()
    {
        global $database;
        $random = random(10);
        foreach ($database->accounts as $account) {
            if ($account->id === $random) return id();
        }
        return $random;
    }

    function salt()
    {
        return random(128);
    }

    function name($name)
    {
        global $database;
        foreach ($database->accounts as $account) {
            if ($account->name === $name) return true;
        }
        return false;
    }

    $result->register = new stdClass();
    $result->register->success = false;
    if (!name($name)) {
        if (strlen($password) >= MINIMUM_PASSWORD_LENGTH) {
            $account = new stdClass();
            $account->id = id();
            $account->name = $name;
            $account->certificates = array();
            $account->saltA = salt();
            $account->saltB = salt();
            $account->hashed = hash("sha256", $account->saltA . $password . $account->saltB);
            array_push($database->accounts, $account);
            save();
            $result->register = new stdClass();
            $result->register->success = true;
        } else {
            $result->errors->registration = "Password too short";
        }
    } else {
        $result->errors->registration = "Name already taken";
    }
}

function save()
{
    global $database;
    file_put_contents(DATABASE, json_encode($database));
}