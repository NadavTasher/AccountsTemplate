const certificateCookie = "certificate";
let success, failure;

function accounts(callback) {
    view("accounts");
    success = (loggedIn = false) => {
        hide("accounts");
        callback(loggedIn);
    };
    failure = () => view("login");
    if (hasCookie(certificateCookie))
        verify(success, failure);
    else
        view("login");
}

function fillForm(form = new FormData()) {
    if (hasCookie(certificateCookie)) {
        form.append("accounts", JSON.stringify({
            action: "verify",
            parameters: {
                certificate: pullCookie(certificateCookie)
            }
        }));
    }
    return form;
}

function force() {
    success();
}

function hasCookie(name) {
    return pullCookie(name) !== undefined;
}

function login(name, password) {

    function error(error) {
        get("login-error").innerText = error;
    }

    let form = new FormData();
    form.append("accounts", JSON.stringify({
        action: "login",
        parameters: {
            name: name,
            password: password
        }
    }));
    fetch("scripts/backend/accounts/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            if (json.hasOwnProperty("login")) {
                if (json.login.hasOwnProperty("success")) {
                    if (json.login.success) {
                        if (json.login.hasOwnProperty("certificate")) {
                            pushCookie(certificateCookie, json.login.certificate);
                            window.location.reload();
                        }
                    }
                }
            }
            if (json.hasOwnProperty("errors") && json.errors.hasOwnProperty("login")) error(json.errors.login);
        });
    });
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

function pushCookie(name, value) {
    const date = new Date();
    date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
    document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + date.toUTCString() + ";domain=" + window.location.hostname + ";path=/";
}

function register(name, password) {

    function error(error) {
        get("register-error").innerText = error;
    }

    let form = new FormData();
    form.append("accounts", JSON.stringify({
        action: "register",
        parameters: {
            name: name,
            password: password
        }
    }));
    fetch("scripts/backend/accounts/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            if (json.hasOwnProperty("register")) {
                if (json.register.hasOwnProperty("success")) {
                    if (json.register.success) {
                        login(name, password);
                    }
                }
            }
            if (json.hasOwnProperty("errors") && json.errors.hasOwnProperty("register")) error(json.errors.register);
        });
    });
}

function verify(success, failure) {
    let form = fillForm();
    fetch("scripts/backend/accounts/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            if (json.hasOwnProperty("verify")) {
                if (json.verify.hasOwnProperty("success")) {
                    if (json.verify.success) {
                        view("app");
                        success(true);
                    } else {
                        failure();
                    }
                }
            } else {
                failure();
            }
        });
    });
}
