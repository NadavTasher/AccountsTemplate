<?php

const DATABASE = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "files" . DIRECTORY_SEPARATOR . "accounts" . DIRECTORY_SEPARATOR . "database.json";
const LOCKOUT_ATTEMPTS = 5;
const LOCKOUT_TIME = 5 * 60;
const MINIMUM_PASSWORD_LENGTH = 8;

const REGISTER_ENABLED = true;
const VERIFY_ENABLED = true;
const LOGIN_ENABLED = true;

$database = json_decode(file_get_contents(DATABASE));
$result = new stdClass();

function accounts()
{
    if (isset($_POST["accounts"])) {
        $information = json_decode(filter($_POST["accounts"]));
        if (isset($information->action) && isset($information->parameters)) {
            $action = $information->action;
            $parameters = $information->parameters;
            switch ($action) {
                case "login":
                    if (isset($parameters->name) && isset($parameters->password)) {
                        if (LOGIN_ENABLED)
                            login($parameters->name, $parameters->password);
                        else
                            error("login", "Login disabled");
                    } else {
                        error("login", "Missing information");
                    }
                    break;
                case "register":
                    if (isset($parameters->name) && isset($parameters->password)) {
                        if (REGISTER_ENABLED)
                            register($parameters->name, $parameters->password);
                        else
                            error("register", "Registration disabled");
                    } else {
                        error("register", "Missing information");
                    }
                    break;
                case "verify":
                    if (isset($parameters->certificate)) {
                        if (VERIFY_ENABLED)
                            return verify($parameters->certificate);
                        else
                            error("verify", "Verify disabled");
                    } else {
                        error("verify", "Missing information");
                    }
                    break;
            }
        }
    }
    return null;
}

function certificate()
{
    global $database;
    $random = random(64);
    foreach ($database as $id => $account) {
        foreach ($account->certificates as $certificate) {
            if ($certificate === $random) return certificate();
        }
    }
    return $random;
}

function error($type, $message)
{
    result("errors", $type, $message);
}

function filter($source)
{
    // Filter inputs from XSS and other attacks
    $source = str_replace("<", "", $source);
    $source = str_replace(">", "", $source);
    return $source;
}

function hashed($password, $saltA, $saltB)
{
    return hash("sha256", $saltA . $password . $saltB);
}

function id()
{
    global $database;
    $random = random(10);
    foreach ($database as $id => $account) {
        if ($id === $random) return id();
    }
    return $random;
}

function lock($id)
{
    global $database;
    $database->$id->lockout->attempts++;
    if ($database->$id->lockout->attempts >= LOCKOUT_ATTEMPTS) {
        $database->$id->lockout->attempts = 0;
        $database->$id->lockout->time = time() + LOCKOUT_TIME;
    }
}

function lockout($id)
{
    global $database;
    return isset($database->$id->lockout->time) && $database->$id->lockout->time > time();
}

function login($name, $password)
{
    global $database;
    $found = false;
    result("login", "success", false);
    foreach ($database as $id => $account) {
        if ($account->name === $name) {
            $found = true;
            if (!lockout($id)) {
                if (password($id, $password)) {
                    $certificate = certificate();
                    array_push($account->certificates, $certificate);
                    result("login", "certificate", $certificate);
                    result("login", "success", true);
                } else {
                    lock($id);
                    error("login", "Incorrect password");
                }
            } else {
                error("login", "Account locked");
            }
        }
    }
    if (!$found)
        error("login", "Account not found");
    save();
}

function name($name)
{
    global $database;
    foreach ($database as $id => $account) {
        if ($account->name === $name) return true;
    }
    return false;
}

function password($id, $password)
{
    global $database;
    return hashed($password, $database->$id->saltA, $database->$id->saltB) === $database->$id->hashed;
}

function random($length)
{
    $current = str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ")[0];
    if ($length > 0) {
        return $current . random($length - 1);
    }
    return "";
}

function register($name, $password)
{
    global $database;
    result("register", "success", false);
    if (!name($name)) {
        if (strlen($password) >= MINIMUM_PASSWORD_LENGTH) {
            $account = new stdClass();
            $account->certificates = array();
            $account->lockout = new stdClass();
            $account->lockout->attempts = 0;
            $account->name = $name;
            $account->saltA = salt();
            $account->saltB = salt();
            $account->hashed = hashed($password, $account->saltA, $account->saltB);
            $id = id();
            $database->$id = $account;
            result("register", "success", true);
        } else {
            error("register", "Password too short");
        }
    } else {
        error("register", "Name already taken");
    }
    save();
}

function result($type, $key, $value)
{
    global $result;
    if (!isset($result->accounts)) $result->accounts = new stdClass();
    if (!isset($result->accounts->$type)) $result->accounts->$type = new stdClass();
    $result->accounts->$type->$key = $value;
}

function salt()
{
    return random(128);
}

function save()
{
    global $database;
    file_put_contents(DATABASE, json_encode($database));
}

function user($id)
{
    global $database;
    $user = $database->$id;
    $user->id = $id;
    unset($user->saltA);
    unset($user->saltB);
    unset($user->hashed);
    unset($user->certificates);
    return $user;
}

function verify($certificate)
{
    global $database;
    result("verify", "success", false);
    foreach ($database as $id => $account) {
        foreach ($account->certificates as $current) {
            if ($current === $certificate) {
                result("verify", "name", $account->name);
                result("verify", "success", true);
                return user($id);
            }
        }
    }
    return null;
}

