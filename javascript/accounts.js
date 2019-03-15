const certificateCookie = "certificate";
let success, failure;

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

function verify(success, failure) {
    let form = new FormData();
    form.append("action", "verify");
    form.append("verify", JSON.stringify({certificate: pullCookie(certificateCookie)}));
    fetch("php/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            let json = JSON.parse(result);
            console.log(json);
            if (json.hasOwnProperty("errors")) {
                if (json.hasOwnProperty("verify")) {
                    if (json.verify.hasOwnProperty("name")) {
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
    let form = new FormData();
    form.append("action", "login");
    form.append("login", JSON.stringify({name: name, password: password}));
    fetch("php/accounts.php", {
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
                            alert(json.errors.login);
                        }
                    }
                } else {
                    if (json.errors.hasOwnProperty("login")) {
                        alert(json.errors.login);
                    }
                }
            }
        });
    });
}

function register(name, password) {
    let form = new FormData();
    form.append("action", "register");
    form.append("register", JSON.stringify({name: name, password: password}));
    fetch("php/accounts.php", {
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
                                alert(json.errors.registration);
                            }
                        }
                    } else {
                        if (json.errors.hasOwnProperty("registration")) {
                            alert(json.errors.registration);
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