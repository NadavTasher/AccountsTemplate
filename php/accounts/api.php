<?php

const REGISTRATION_ENABLED = true;
const MINIMUM_PASSWORD_LENGTH = 8;
const LOCKOUT_TIME = 5 * 60;
const LOCKOUT_ATTEMPTS = 5;

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
    $accountFound = false;
    $result->login = new stdClass();
    $result->login->success = false;
    foreach ($database->accounts as $account) {
        if ($account->name === $name) {
            $accountFound = true;
            if (!lockout($account)) {
                if (password($account, $password)) {
                    $certificate = certificate();
                    $result->login->certificate = $certificate;
                    array_push($account->certificates, $certificate);
                    save();
                    $result->login->success = true;
                } else {
                    lock($account);
                    $result->errors->login = "Incorrect password";
                }
            } else {
                $result->errors->login = "Account locked";
            }
        }
    }
    if (!$accountFound)
        $result->errors->login = "Account not found";
}

function register($name, $password)
{
    global $result, $database;
    $result->register = new stdClass();
    $result->register->success = false;
    if (REGISTRATION_ENABLED) {
        if (!name($name)) {
            if (strlen($password) >= MINIMUM_PASSWORD_LENGTH) {
                $account = new stdClass();
                $account->id = id();
                $account->name = $name;
                $account->certificates = array();
                $account->lockout = new stdClass();
                $account->saltA = salt();
                $account->saltB = salt();
                $account->hashed = hash("sha256", $account->saltA . $password . $account->saltB);
                array_push($database->accounts, $account);
                save();
                $result->register->success = true;
            } else {
                $result->errors->registration = "Password too short";
            }
        } else {
            $result->errors->registration = "Name already taken";
        }
    } else {
        $result->errors->registration = "Registration disabled";
    }
}

function save()
{
    global $database;
    file_put_contents(DATABASE, json_encode($database));
}

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

function password($account, $password)
{
    return hash("sha256", $account->saltA . $password . $account->saltB) === $account->hashed;
}

function lockout($account)
{
    return isset($account->lockout->time) && $account->lockout->time > time();
}

function lock($account)
{
    if (!isset($account->lockout->attempts)) $account->lockout->attempts = 0;
    $account->lockout->attempts++;
    if ($account->lockout->attempts >= LOCKOUT_ATTEMPTS) {
        $account->lockout->attempts = 0;
        $account->lockout->time = time() + LOCKOUT_TIME;
    }
    save();
}

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