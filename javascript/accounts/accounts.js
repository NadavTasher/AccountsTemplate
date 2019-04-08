const certificateCookie = "certificate";
let success, failure, loggedIn = false;

function accounts(callback) {
    view("accounts");
    success = () => {
        hide("accounts");
        callback();
    };
    failure = () => view("login");
    if (hasCookie(certificateCookie))
        verify(success, failure);
    else
        view("login");
}

function fillForm(form = new FormData()) {
    if (hasCookie(certificateCookie)) {
        form.append("action", "verify");
        form.append("verify", JSON.stringify({certificate: pullCookie(certificateCookie)}));
    }
    return form;
}

function force() {
    success();
}

function isLoggedIn() {
    return loggedIn;
}

function verify(success, failure) {
    let form = fillForm();
    fetch("php/accounts/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            if (json.hasOwnProperty("errors")) {
                if (json.hasOwnProperty("verify")) {
                    if (json.verify.hasOwnProperty("name")) {
                        loggedIn = true;
                        success();
                    } else {
                        failure();
                    }
                } else {
                    failure();
                }
            }
        });
    });
}

function login(name, password) {

    function error(error) {
        get("login-error").innerText = error;
    }

    let form = new FormData();
    form.append("action", "login");
    form.append("login", JSON.stringify({name: name, password: password}));
    fetch("php/accounts/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            if (json.hasOwnProperty("errors")) {
                if (json.hasOwnProperty("login")) {
                    if (json.login.hasOwnProperty("certificate")) {
                        pushCookie(certificateCookie, json.login.certificate);
                        window.location.reload();
                    } else {
                        if (json.errors.hasOwnProperty("login")) {
                            error(json.errors.login);
                        }
                    }
                } else {
                    if (json.errors.hasOwnProperty("login")) {
                        error(json.errors.login);
                    }
                }
            }
        });
    });
}

function register(name, password) {

    function error(error) {
        get("register-error").innerText = error;
    }

    let form = new FormData();
    form.append("action", "register");
    form.append("register", JSON.stringify({name: name, password: password}));
    fetch("php/accounts/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            if (json.hasOwnProperty("errors")) {
                if (json.hasOwnProperty("register")) {
                    if (json.register.hasOwnProperty("success")) {
                        if (json.register.success === true) {
                            login(name, password);
                        } else {
                            if (json.errors.hasOwnProperty("registration")) {
                                error(json.errors.registration);
                            }
                        }
                    } else {
                        if (json.errors.hasOwnProperty("registration")) {
                            error(json.errors.registration);
                        }
                    }
                } else {
                    if (json.errors.hasOwnProperty("registration")) {
                        alert(json.errors.registration);
                    }
                }
            }
        });
    });
}

function pushCookie(name, value) {
    const date = new Date();
    date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
    document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + date.toUTCString() + ";domain=" + window.location.hostname + ";path=/";
}

function pullCookie(name) {
    name += "=";
    const cookies = document.cookie.split(';');
    for (let i = 0; i < cookies.length; i++) {
        let cookie = cookies[i];
        while (cookie.charAt(0) === ' ') {
            cookie = cookie.substring(1);
        }
        if (cookie.indexOf(name) === 0) {
            return decodeURIComponent(cookie.substring(name.length, cookie.length));
        }
    }
    return undefined;
}

function hasCookie(name) {
    return pullCookie(name) !== undefined;
}