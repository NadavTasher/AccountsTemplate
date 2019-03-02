const certificateCookie = "certificate";
let postLogin;

function accounts(callback) {
    postLogin = callback;
    if (hasCookie(certificateCookie))

        postLogin();
    else
        view("login");
}

function verify(callback){

}

function login(name, password) {
    let form = new FormData();
    form.append("action", "login");
    form.append("name", name);
    form.append("password", password);
    fetch("php/accounts.php", {
        method: "post",
        body: form
    }).then(response => {
        response.text().then((result) => {
            console.log(result.json());
        });
    });
}

function register(name, password) {

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