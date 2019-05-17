<?php

const ACCOUNTS_DATABASE = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "files" . DIRECTORY_SEPARATOR . "accounts" . DIRECTORY_SEPARATOR . "database.json";
const ACCOUNTS_LOCKOUT_ATTEMPTS = 5;
const ACCOUNTS_LOCKOUT_TIME = 5 * 60;
const ACCOUNTS_MINIMUM_PASSWORD_LENGTH = 8;

const ACCOUNTS_REGISTER_ENABLED = true;
const ACCOUNTS_VERIFY_ENABLED = true;
const ACCOUNTS_LOGIN_ENABLED = true;

$accounts_database = json_decode(file_get_contents(ACCOUNTS_DATABASE));
$result = new stdClass();

function accounts()
{
    if (isset($_POST["accounts"])) {
        $information = json_decode(accounts_filter($_POST["accounts"]));
        if (isset($information->action) && isset($information->parameters)) {
            $action = $information->action;
            $parameters = $information->parameters;
            switch ($action) {
                case "login":
                    if (isset($parameters->name) && isset($parameters->password)) {
                        if (ACCOUNTS_LOGIN_ENABLED)
                            accounts_login($parameters->name, $parameters->password);
                        else
                            accounts_error("login", "Login disabled");
                    } else {
                        accounts_error("login", "Missing information");
                    }
                    break;
                case "register":
                    if (isset($parameters->name) && isset($parameters->password)) {
                        if (ACCOUNTS_REGISTER_ENABLED)
                            accounts_register($parameters->name, $parameters->password);
                        else
                            accounts_error("register", "Registration disabled");
                    } else {
                        accounts_error("register", "Missing information");
                    }
                    break;
                case "verify":
                    if (isset($parameters->certificate)) {
                        if (ACCOUNTS_VERIFY_ENABLED)
                            return accounts_verify($parameters->certificate);
                        else
                            accounts_error("verify", "Verify disabled");
                    } else {
                        accounts_error("verify", "Missing information");
                    }
                    break;
            }
            accounts_save();
        }
    }
    return null;
}

function accounts_certificate()
{
    global $accounts_database;
    $random = accounts_random(64);
    foreach ($accounts_database as $id => $account) {
        foreach ($account->certificates as $certificate) {
            if ($certificate === $random) return accounts_certificate();
        }
    }
    return $random;
}

function accounts_error($type, $message)
{
    accounts_result("errors", $type, $message);
}

function accounts_filter($source)
{
    // Filter inputs from XSS and other attacks
    $source = str_replace("<", "", $source);
    $source = str_replace(">", "", $source);
    return $source;
}

function accounts_hashed($password, $saltA, $saltB)
{
    return hash("sha256", $saltA . $password . $saltB);
}

function accounts_id()
{
    global $accounts_database;
    $random = accounts_random(10);
    if (isset($accounts_database->$random)) return accounts_id();
    return $random;
}

function accounts_lock($id)
{
    global $accounts_database;
    $accounts_database->$id->lockout->attempts++;
    if ($accounts_database->$id->lockout->attempts >= ACCOUNTS_LOCKOUT_ATTEMPTS) {
        $accounts_database->$id->lockout->attempts = 0;
        $accounts_database->$id->lockout->time = time() + ACCOUNTS_LOCKOUT_TIME;
    }
}

function accounts_lockout($id)
{
    global $accounts_database;
    return isset($accounts_database->$id->lockout->time) && $accounts_database->$id->lockout->time > time();
}

function accounts_login($name, $password)
{
    global $accounts_database;
    $found = false;
    accounts_result("login", "success", false);
    foreach ($accounts_database as $id => $account) {
        if ($account->name === $name) {
            $found = true;
            if (!accounts_lockout($id)) {
                if (accounts_password($id, $password)) {
                    $certificate = accounts_certificate();
                    array_push($account->certificates, $certificate);
                    accounts_result("login", "certificate", $certificate);
                    accounts_result("login", "success", true);
                } else {
                    accounts_lock($id);
                    accounts_error("login", "Incorrect password");
                }
            } else {
                accounts_error("login", "Account locked");
            }
        }
    }
    if (!$found)
        accounts_error("login", "Account not found");
}

function accounts_name($name)
{
    global $accounts_database;
    foreach ($accounts_database as $id => $account) {
        if ($account->name === $name) return true;
    }
    return false;
}

function accounts_password($id, $password)
{
    global $accounts_database;
    return accounts_hashed($password, $accounts_database->$id->saltA, $accounts_database->$id->saltB) === $accounts_database->$id->hashed;
}

function accounts_random($length)
{
    $current = str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ")[0];
    if ($length > 0) {
        return $current . accounts_random($length - 1);
    }
    return "";
}

function accounts_register($name, $password)
{
    global $accounts_database;
    accounts_result("register", "success", false);
    if (!accounts_name($name)) {
        if (strlen($password) >= ACCOUNTS_MINIMUM_PASSWORD_LENGTH) {
            $account = new stdClass();
            $account->certificates = array();
            $account->lockout = new stdClass();
            $account->lockout->attempts = 0;
            $account->name = $name;
            $account->saltA = accounts_salt();
            $account->saltB = accounts_salt();
            $account->hashed = accounts_hashed($password, $account->saltA, $account->saltB);
            $id = accounts_id();
            $accounts_database->$id = $account;
            accounts_result("register", "success", true);
        } else {
            accounts_error("register", "Password too short");
        }
    } else {
        accounts_error("register", "Name already taken");
    }
}

function accounts_result($type, $key, $value)
{
    global $result;
    if (!isset($result->accounts)) $result->accounts = new stdClass();
    if (!isset($result->accounts->$type)) $result->accounts->$type = new stdClass();
    $result->accounts->$type->$key = $value;
}

function accounts_salt()
{
    return accounts_random(128);
}

function accounts_save()
{
    global $accounts_database;
    file_put_contents(ACCOUNTS_DATABASE, json_encode($accounts_database));
}

function accounts_user($id)
{
    global $accounts_database;
    $user = $accounts_database->$id;
    $user->id = $id;
    unset($user->saltA);
    unset($user->saltB);
    unset($user->hashed);
    unset($user->certificates);
    return $user;
}

function accounts_verify($certificate)
{
    global $accounts_database;
    accounts_result("verify", "success", false);
    foreach ($accounts_database as $id => $account) {
        foreach ($account->certificates as $current) {
            if ($current === $certificate) {
                accounts_result("verify", "name", $account->name);
                accounts_result("verify", "success", true);
                return accounts_user($id);
            }
        }
    }
    return null;
}

