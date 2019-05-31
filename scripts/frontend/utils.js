function view(v) {
    let element = get(v);
    let parent = element.parentNode;
    for (let n = 0; n < parent.children.length; n++) {
        hide(parent.children[n]);
    }
    show(element);
}

function hide(v) {
    get(v).style.display = "none";
}

function show(v) {
    get(v).style.removeProperty("display");
}

function get(v) {
    return (typeof "" === typeof v || typeof '' === typeof v) ? document.getElementById(v) : v;
}

function visible(v) {
    return (get(v).style.getPropertyValue("display") !== "none");
}

function clear(v) {
    let view = get(v);
    while (view.firstChild) {
        view.removeChild(view.firstChild);
    }
}

function title(title) {
    document.title = title;
}

function theme(color) {
    let meta = document.getElementsByTagName("meta")["theme-color"];
    if (meta !== undefined) {
        meta.content = color;
    } else {
        meta = document.createElement("meta");
        meta.name = "theme-color";
        meta.content = color;
        document.head.appendChild(meta);
    }

}

function download(file, data, type = "text/plain", encoding = "utf8") {
    let link = document.createElement("a");
    link.download = file;
    link.href = "data:" + type + ";" + encoding + "," + data;
    link.click();
}

function slideIn(v, direction = true, callback = undefined) {
    slide(v, (direction ? -1 : 1) * (get(v).offsetWidth ), 0, true, callback);
}

function slideOut(v, direction = true, callback = undefined) {
    slide(v, 0, (direction ? -1 : 1) * (get(v).offsetWidth ), false, callback);
}

function slide(v, from, to, release = true, callback = undefined) {
    let view = get(v);
    let current = -(from);
    let interval = setInterval(function () {
        if (current !== to) {
            if (current < to)
                current++;
            else
                current--;
            view.style.position = "relative";
            view.style.left = current + "px";
        } else {
            clearInterval(interval);
            if (release)
                view.style.removeProperty("position");
            if (callback !== undefined) callback();
        }
    }, 1);
}

function gestures(up, down, left, right, upgoing, downgoing, leftgoing, rightgoing) {
    let touchX, touchY, deltaX, deltaY;
    document.ontouchstart = (event) => {
        touchX = event.touches[0].clientX;
        touchY = event.touches[0].clientY;
    };
    document.ontouchmove = (event) => {
        deltaX = touchX - event.touches[0].clientX;
        deltaY = touchY - event.touches[0].clientY;
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            if (deltaX > 0) {
                if (leftgoing !== undefined) leftgoing();
            } else {
                if (rightgoing !== undefined) rightgoing();
            }
        } else {
            if (deltaY > 0) {
                if (upgoing !== undefined) upgoing();
            } else {
                if (downgoing !== undefined) downgoing();
            }
        }

    };
    document.ontouchend = () => {
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            if (deltaX > 0) {
                if (left !== undefined) left();
            } else {
                if (right !== undefined) right();
            }
        } else {
            if (deltaY > 0) {
                if (up !== undefined) up();
            } else {
                if (down !== undefined) down();
            }
        }
        touchX = undefined;
        touchY = undefined;
    };
}

function worker(w = "worker.js") {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(w).then((result) => {
        });
    }
}